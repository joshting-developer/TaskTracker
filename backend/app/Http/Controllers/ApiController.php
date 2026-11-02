<?php

namespace App\Http\Controllers;

use App\Authorization\WorkspacePermissions;
use App\Domain\DomainError;
use App\Services\AccountService;
use App\Services\SmtpService;
use App\Services\Todos;
use App\Services\WorkspaceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function auth(Request $request, string $operation, AccountService $accounts)
    {
        return DB::transaction(function () use ($request, $operation, $accounts) {
            if ($operation === 'logout') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $data = null;
            } elseif ($operation === 'current') $data = $accounts->context($request);
            elseif (in_array($operation, ['register', 'login'], true)) $data = $accounts->$operation($request, $this->input($request));
            else DomainError::fail('NOT_FOUND', '找不到這個操作');
            return response()->json(['ok' => true, 'data' => $data])->header('X-CSRF-TOKEN', csrf_token());
        });
    }

    public function rpc(Request $request, string $operation, AccountService $accounts, SmtpService $smtp)
    {
        $input = $this->input($request);
        $data = DB::transaction(function () use ($request, $operation, $accounts, $input, $smtp) {
            $context = $accounts->context($request);
            if (in_array($operation, Todos::OPERATIONS, true)) return (new Todos($context))->execute($operation, $input);
            if ($operation === 'inviteMember') return $accounts->invite($context, $input);
            if (in_array($operation, ['smtpStatus', 'verifySmtp'], true)) {
                WorkspacePermissions::authorize($context, 'smtp.verify');
                return $operation === 'smtpStatus' ? $smtp->status() : $smtp->verify();
            }
            return (new WorkspaceService($context))->execute($operation, $input);
        });
        return response()->json(['ok' => true, 'data' => $data]);
    }

    private function input(Request $request): array
    {
        if (!$request->isJson()) DomainError::fail('INVALID_INPUT', '需要 JSON 資料');
        if (strlen($request->getContent()) > 50000) DomainError::fail('INVALID_INPUT', '資料太大');
        try { $value = json_decode($request->getContent(), flags: JSON_THROW_ON_ERROR); }
        catch (\JsonException) { DomainError::fail('INVALID_INPUT', 'JSON 格式不正確'); }
        if (!$value instanceof \stdClass) DomainError::fail('INVALID_INPUT', '需要 JSON 物件');
        return json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}
