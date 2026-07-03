<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class SettingsController extends BaseController
{
    public function get()
    {
        $setting = DB::selectOne('SELECT TOP 1 * FROM StoreSettings ORDER BY Id');
        if ($setting) {
            $setting->BankAccounts = $setting->BankAccountsJson ? json_decode($setting->BankAccountsJson, true) : [];
        }
        return response()->json($setting ?: []);
    }

    public function pickupBranches()
    {
        $rows = DB::select('SELECT Id, Code, Name, Address FROM Warehouses WHERE IsActive = 1 ORDER BY Id');
        return response()->json($rows);
    }

    public function update(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        $roles = $claims['roles'] ?? ($claims['role'] ?? null);
        if (!self::hasAdminRole($roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        $setting = DB::selectOne('SELECT TOP 1 * FROM StoreSettings ORDER BY Id');
        if (!$setting) {
            return response()->json(['message' => 'Setting not found'], 500);
        }

        DB::table('StoreSettings')->where('Id', $setting->Id)->update([
            'StoreName' => $payload['storeName'] ?? $setting->StoreName,
            'Hotline' => $payload['hotline'] ?? $setting->Hotline,
            'SupportEmail' => $payload['supportEmail'] ?? $setting->SupportEmail,
            'Address' => $payload['address'] ?? $setting->Address,
            'WarrantyAddress' => $payload['warrantyAddress'] ?? $setting->WarrantyAddress,
            'DefaultShippingFee' => $payload['defaultShippingFee'] ?? $setting->DefaultShippingFee,
            'FreeShippingThreshold' => $payload['freeShippingThreshold'] ?? $setting->FreeShippingThreshold,
            'SupportTime' => $payload['supportTime'] ?? $setting->SupportTime,
            'LogoUrl' => $payload['logoUrl'] ?? $setting->LogoUrl,
            'FacebookUrl' => $payload['facebookUrl'] ?? $setting->FacebookUrl,
            'ZaloUrl' => $payload['zaloUrl'] ?? $setting->ZaloUrl,
            'BankName' => $payload['bankName'] ?? $setting->BankName,
            'BankAccountNumber' => $payload['bankAccountNumber'] ?? $setting->BankAccountNumber,
            'BankAccountHolder' => $payload['bankAccountHolder'] ?? $setting->BankAccountHolder,
            'BankAccountsJson' => isset($payload['bankAccounts']) ? json_encode($payload['bankAccounts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $setting->BankAccountsJson,
            'UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $updated = DB::selectOne('SELECT TOP 1 * FROM StoreSettings ORDER BY Id');
        if ($updated) $updated->BankAccounts = $updated->BankAccountsJson ? json_decode($updated->BankAccountsJson, true) : [];
        return response()->json($updated);
    }

    private static function hasAdminRole($roles): bool
    {
        if (is_string($roles)) {
            return $roles === 'Admin';
        }
        if (is_array($roles)) {
            return in_array('Admin', $roles, true);
        }
        return false;
    }
}
