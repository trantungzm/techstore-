<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class NotificationController extends BaseController
{
    public function templates(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // ensure tables exist — simple: run create statements if missing
        // For brevity we'll assume DB structure exists or will be migrated
        $rows = DB::select('SELECT Id, Code, Name, Channel, TitleTemplate, BodyTemplate, IsActive, CreatedAt, UpdatedAt FROM NotificationTemplates ORDER BY Id DESC');
        return response()->json($rows);
    }

    public function createTemplate(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::table('NotificationTemplates')->insertGetId([
            'Code' => $payload['code'] ?? '',
            'Name' => $payload['name'] ?? '',
            'Channel' => $payload['channel'] ?? 'System',
            'TitleTemplate' => $payload['titleTemplate'] ?? '',
            'BodyTemplate' => $payload['bodyTemplate'] ?? '',
            'IsActive' => isset($payload['isActive']) ? ($payload['isActive'] ? 1 : 0) : 1,
            'CreatedAt' => $now,
            'UpdatedAt' => $now,
        ]);

        $template = DB::selectOne('SELECT * FROM NotificationTemplates WHERE Id = ?', [$id]);
        return response()->json($template, 201);
    }

    public function updateTemplate(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exists = DB::selectOne('SELECT Id FROM NotificationTemplates WHERE Id = ?', [$id]);
        if (!$exists) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        $payload = $request->json()->all();
        DB::table('NotificationTemplates')->where('Id', $id)->update([
            'Code' => $payload['code'] ?? '',
            'Name' => $payload['name'] ?? '',
            'Channel' => $payload['channel'] ?? 'System',
            'TitleTemplate' => $payload['titleTemplate'] ?? '',
            'BodyTemplate' => $payload['bodyTemplate'] ?? '',
            'IsActive' => isset($payload['isActive']) ? ($payload['isActive'] ? 1 : 0) : 1,
            'UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $template = DB::selectOne('SELECT * FROM NotificationTemplates WHERE Id = ?', [$id]);
        return response()->json($template);
    }

    public function deleteTemplate(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exists = DB::selectOne('SELECT Id FROM NotificationTemplates WHERE Id = ?', [$id]);
        if (!$exists) {
            return response()->json(['message' => 'Template not found'], 404);
        }

        DB::table('NotificationTemplates')->where('Id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function campaigns(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = DB::select('SELECT c.Id, c.Name, c.TemplateId, c.PayloadJson, c.Audience, c.Status, c.CreatedAt FROM NotificationCampaigns c ORDER BY c.Id DESC');
        return response()->json($rows);
    }

    public function createCampaign(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        $template = DB::selectOne('SELECT * FROM NotificationTemplates WHERE Id = ?', [(int)($payload['templateId'] ?? 0)]);
        if (!$template || !$template->IsActive) {
            return response()->json(['message' => 'Template not found or inactive'], 400);
        }

        $now = gmdate('Y-m-d H:i:s');
        $campaignId = DB::table('NotificationCampaigns')->insertGetId([
            'Name' => $payload['name'] ?? '',
            'TemplateId' => (int)($payload['templateId'] ?? 0),
            'PayloadJson' => isset($payload['payload']) ? json_encode($payload['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'Audience' => $payload['audience'] ?? 'SingleUser',
            'Status' => 'Created',
            'CreatedAt' => $now,
        ]);

        DB::table('NotificationJobs')->insert([
            'CampaignId' => $campaignId,
            'TemplateId' => (int)($payload['templateId'] ?? 0),
            'UserId' => $payload['userId'] ?? null,
            'Title' => $payload['title'] ?? $template->TitleTemplate,
            'Message' => $payload['message'] ?? $template->BodyTemplate,
            'PayloadJson' => isset($payload['payload']) ? json_encode($payload['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'Status' => 'Pending',
            'RetryCount' => 0,
            'AvailableAt' => $now,
            'CreatedAt' => $now,
        ]);

        return response()->json(['id' => $campaignId, 'status' => 'Created'], 201);
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
