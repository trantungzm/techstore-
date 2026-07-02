<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../validators.php';

function handleNotifications(string $method, string $path): void
{
    if ($path === '/api/admin/notification-templates' && $method === 'GET') {
        requireAdmin();
        ensureNotificationsAdminTables();
        jsonResponse(fetchAll(
            'SELECT Id, Code, Name, Channel, TitleTemplate, BodyTemplate, IsActive, CreatedAt, UpdatedAt
             FROM NotificationTemplates
             ORDER BY Id DESC'
        ));
    }

    if ($path === '/api/admin/notification-templates' && $method === 'POST') {
        requireAdmin();
        ensureNotificationsAdminTables();
        $payload = validateTemplatePayload(readJsonBody(), false);
        $statement = db()->prepare(
            'INSERT INTO NotificationTemplates
                (Code, Name, Channel, TitleTemplate, BodyTemplate, IsActive, CreatedAt, UpdatedAt)
             VALUES
                (:Code, :Name, :Channel, :TitleTemplate, :BodyTemplate, :IsActive, :CreatedAt, :UpdatedAt)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            ':Code' => $payload['code'],
            ':Name' => $payload['name'],
            ':Channel' => $payload['channel'],
            ':TitleTemplate' => $payload['titleTemplate'],
            ':BodyTemplate' => $payload['bodyTemplate'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':CreatedAt' => $now,
            ':UpdatedAt' => $now,
        ]);

        $id = (int) db()->lastInsertId();
        jsonResponse(fetchTemplateById($id), 201);
    }

    if (preg_match('#^/api/admin/notification-templates/(\d+)$#', $path, $matches) === 1 && $method === 'PUT') {
        requireAdmin();
        ensureNotificationsAdminTables();
        $id = (int) $matches[1];
        if (fetchTemplateById($id) === null) {
            jsonResponse(['message' => 'Template not found'], 404);
        }

        $payload = validateTemplatePayload(readJsonBody(), true);
        $statement = db()->prepare(
            'UPDATE NotificationTemplates SET
                Code = :Code,
                Name = :Name,
                Channel = :Channel,
                TitleTemplate = :TitleTemplate,
                BodyTemplate = :BodyTemplate,
                IsActive = :IsActive,
                UpdatedAt = :UpdatedAt
             WHERE Id = :Id'
        );
        $statement->execute([
            ':Id' => $id,
            ':Code' => $payload['code'],
            ':Name' => $payload['name'],
            ':Channel' => $payload['channel'],
            ':TitleTemplate' => $payload['titleTemplate'],
            ':BodyTemplate' => $payload['bodyTemplate'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        jsonResponse(fetchTemplateById($id));
    }

    if (preg_match('#^/api/admin/notification-templates/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        requireAdmin();
        ensureNotificationsAdminTables();
        $id = (int) $matches[1];
        if (fetchTemplateById($id) === null) {
            jsonResponse(['message' => 'Template not found'], 404);
        }
        $statement = db()->prepare('DELETE FROM NotificationTemplates WHERE Id = :Id');
        $statement->execute([':Id' => $id]);
        jsonResponse(['success' => true]);
    }

    if ($path === '/api/admin/notifications/campaigns' && $method === 'GET') {
        requireAdmin();
        ensureNotificationsAdminTables();
        jsonResponse(fetchAll(
            'SELECT c.Id, c.Name, c.TemplateId, c.PayloadJson, c.Audience, c.Status, c.CreatedAt
             FROM NotificationCampaigns c
             ORDER BY c.Id DESC'
        ));
    }

    if ($path === '/api/admin/notifications/campaigns' && $method === 'POST') {
        requireAdmin();
        ensureNotificationsAdminTables();
        $payload = validateCampaignPayload(readJsonBody());

        $template = fetchTemplateById((int) $payload['templateId']);
        if ($template === null || !toBool($template['IsActive'])) {
            jsonResponse(['message' => 'Template not found or inactive'], 400);
        }

        $statement = db()->prepare(
            'INSERT INTO NotificationCampaigns
                (Name, TemplateId, PayloadJson, Audience, Status, CreatedAt)
             VALUES
                (:Name, :TemplateId, :PayloadJson, :Audience, :Status, :CreatedAt)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            ':Name' => $payload['name'],
            ':TemplateId' => (int) $payload['templateId'],
            ':PayloadJson' => $payload['payloadJson'],
            ':Audience' => $payload['audience'],
            ':Status' => 'Created',
            ':CreatedAt' => $now,
        ]);
        $campaignId = (int) db()->lastInsertId();

        $jobStatement = db()->prepare(
            'INSERT INTO NotificationJobs
                (CampaignId, TemplateId, UserId, Title, Message, PayloadJson, Status, RetryCount, AvailableAt, CreatedAt)
             VALUES
                (:CampaignId, :TemplateId, :UserId, :Title, :Message, :PayloadJson, :Status, :RetryCount, :AvailableAt, :CreatedAt)'
        );
        $jobStatement->execute([
            ':CampaignId' => $campaignId,
            ':TemplateId' => (int) $payload['templateId'],
            ':UserId' => $payload['userId'],
            ':Title' => $payload['title'] ?? $template['TitleTemplate'],
            ':Message' => $payload['message'] ?? $template['BodyTemplate'],
            ':PayloadJson' => $payload['payloadJson'],
            ':Status' => 'Pending',
            ':RetryCount' => 0,
            ':AvailableAt' => $now,
            ':CreatedAt' => $now,
        ]);

        jsonResponse([
            'id' => $campaignId,
            'status' => 'Created',
        ], 201);
    }
}
