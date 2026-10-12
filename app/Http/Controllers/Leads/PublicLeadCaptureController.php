<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Models\LeadCaptureForm;
use App\Services\LeadCaptureService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicLeadCaptureController extends Controller
{
    public function __invoke(Request $request, string $formKey, LeadCaptureService $capture): Response
    {
        $form = LeadCaptureForm::query()->where('form_key', $formKey)->where('is_active', true)->first();
        abort_unless($form, 404);

        $result = $capture->submitPublicForm($form, $request);
        $message = $form->success_message ?: 'Thank you! We will be in touch shortly.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $message,
                'redirect_url' => $form->redirect_url,
            ], $result['spam'] ? 200 : ($result['lead']?->wasRecentlyCreated ? 201 : 200));
        }

        if ($form->redirect_url) {
            return redirect()->away($form->redirect_url);
        }

        return response($message, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
