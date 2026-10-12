<?php

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeDocumentType;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDocumentService
{
    public const ALLOWED_MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const MAX_KB = 5120;

    public function __construct(protected ActivityLogger $activityLogger)
    {
    }

    public function upload(Employee $employee, EmployeeDocumentType $type, UploadedFile $file, User $actor): EmployeeDocument
    {
        if ((int) $type->company_id !== (int) $employee->company_id) {
            throw ValidationException::withMessages(['type' => 'Document type belongs to another company.']);
        }

        if ($file->getSize() > self::MAX_KB * 1024) {
            throw ValidationException::withMessages(['file' => 'File exceeds size limit.']);
        }

        $mime = $file->getMimeType() ?: '';
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages(['file' => 'File type not allowed.']);
        }

        $version = (int) EmployeeDocument::where('employee_id', $employee->id)
            ->where('employee_document_type_id', $type->id)
            ->max('version') + 1;

        $path = $file->store('hr/employee-documents/'.$employee->company_id.'/'.$employee->id, 'local');
        $safeName = basename(str_replace(["\0", '\\'], '', $file->getClientOriginalName() ?: 'document'));
        $safeName = preg_replace('/[^\w.\- ()\[\]]+/u', '_', $safeName) ?: 'document';

        $doc = EmployeeDocument::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'employee_document_type_id' => $type->id,
            'original_name' => $safeName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'version' => $version,
            'status' => 'pending',
            'uploaded_by' => $actor->id,
        ]);

        $this->activityLogger->log('employee_document_uploaded', 'hr', $doc);

        return $doc;
    }

    public function authorizeDownload(User $actor, EmployeeDocument $document): void
    {
        if ($actor->isSuperAdmin() || $actor->can('hr.documents.review')) {
            return;
        }

        $employee = $actor->employee;
        if ($employee && (int) $employee->id === (int) $document->employee_id) {
            return;
        }

        if ($actor->can('hr.documents.view_team') && $employee) {
            $target = $document->employee;
            if ($target && (int) $target->reporting_manager_id === (int) $employee->id) {
                // Managers may see non-sensitive checklist status; salary/bank docs still restricted by type code later
                return;
            }
        }

        abort(403);
    }

    public function download(User $actor, EmployeeDocument $document): StreamedResponse
    {
        $this->authorizeDownload($actor, $document);

        if (! Storage::disk($document->disk)->exists($document->path)) {
            abort(404);
        }

        $this->activityLogger->log('employee_document_downloaded', 'hr', $document);

        return Storage::disk($document->disk)->download($document->path, $document->original_name);
    }
}
