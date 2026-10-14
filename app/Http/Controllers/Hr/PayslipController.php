<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Models\PayrollItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PayslipController extends Controller
{
    public function show(Request $request, PayrollItem $payrollItem): Response
    {
        $user = $request->user();
        $own = $user->employee && (int) $user->employee->id === (int) $payrollItem->employee_id;
        abort_unless(
            $own || $user->can('hr.payroll.view') || $user->isSuperAdmin(),
            403
        );
        abort_if($payrollItem->payment_status === 'blocked', 422, 'This payslip is blocked because salary is not configured.');

        $payrollItem->load(['employee.department', 'employee.designation', 'run']);
        $settings = BillingSetting::current();

        return Pdf::loadView('pdf.payslip', [
            'item' => $payrollItem,
            'settings' => $settings,
            'employee' => $payrollItem->employee,
            'run' => $payrollItem->run,
        ])->download('payslip-'.$payrollItem->employee->employee_code.'-'.$payrollItem->run->run_code.'.pdf');
    }
}
