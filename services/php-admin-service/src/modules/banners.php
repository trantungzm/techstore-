<?php

declare(strict_types=1);

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../validators.php';

function handleBanners(string $method, string $path): void
{
    if ($path === '/api/banners/active' && $method === 'GET') {
        jsonResponse(fetchAll(
            'SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
             FROM Banners
             WHERE IsActive = 1
             ORDER BY DisplayOrder, Id'
        ));
    }

    if ($path === '/api/banners' && $method === 'GET') {
        requireAdmin();
        jsonResponse(fetchAll(
            'SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
             FROM Banners
             ORDER BY DisplayOrder, Id'
        ));
    }

    if ($path === '/api/banners' && $method === 'POST') {
        requireAdmin();
        $payload = validateBannerPayload(readJsonBody(), false);
        $pdo = db();
        $statement = $pdo->prepare(
            'INSERT INTO Banners
                (Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt)
             VALUES
                (:Kicker, :Title, :SubTitle, :CtaLabel, :CtaTo, :ImageUrl, :OfferTitle, :OfferDiscount, :OfferProduct, :DisplayOrder, :IsActive, :CreatedAt, :UpdatedAt)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            ':Kicker' => $payload['kicker'],
            ':Title' => $payload['title'],
            ':SubTitle' => $payload['subTitle'],
            ':CtaLabel' => $payload['ctaLabel'],
            ':CtaTo' => $payload['ctaTo'],
            ':ImageUrl' => $payload['imageUrl'],
            ':OfferTitle' => $payload['offerTitle'],
            ':OfferDiscount' => $payload['offerDiscount'],
            ':OfferProduct' => $payload['offerProduct'],
            ':DisplayOrder' => $payload['displayOrder'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':CreatedAt' => $now,
            ':UpdatedAt' => null,
        ]);

        $id = (int) $pdo->lastInsertId();
        jsonResponse(fetchBannerById($id), 201);
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'GET') {
        requireAdmin();
        $banner = fetchBannerById((int) $matches[1]);
        if ($banner === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }
        jsonResponse($banner);
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'PUT') {
        requireAdmin();
        $id = (int) $matches[1];
        if (fetchBannerById($id) === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }

        $payload = validateBannerPayload(readJsonBody(), true);
        $statement = db()->prepare(
            'UPDATE Banners SET
                Kicker = :Kicker,
                Title = :Title,
                SubTitle = :SubTitle,
                CtaLabel = :CtaLabel,
                CtaTo = :CtaTo,
                ImageUrl = :ImageUrl,
                OfferTitle = :OfferTitle,
                OfferDiscount = :OfferDiscount,
                OfferProduct = :OfferProduct,
                DisplayOrder = :DisplayOrder,
                IsActive = :IsActive,
                UpdatedAt = :UpdatedAt
             WHERE Id = :Id'
        );
        $statement->execute([
            ':Id' => $id,
            ':Kicker' => $payload['kicker'],
            ':Title' => $payload['title'],
            ':SubTitle' => $payload['subTitle'],
            ':CtaLabel' => $payload['ctaLabel'],
            ':CtaTo' => $payload['ctaTo'],
            ':ImageUrl' => $payload['imageUrl'],
            ':OfferTitle' => $payload['offerTitle'],
            ':OfferDiscount' => $payload['offerDiscount'],
            ':OfferProduct' => $payload['offerProduct'],
            ':DisplayOrder' => $payload['displayOrder'],
            ':IsActive' => $payload['isActive'] ? 1 : 0,
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        jsonResponse(fetchBannerById($id));
    }

    if (preg_match('#^/api/banners/(\d+)$#', $path, $matches) === 1 && $method === 'DELETE') {
        requireAdmin();
        $id = (int) $matches[1];
        if (fetchBannerById($id) === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }
        $statement = db()->prepare('DELETE FROM Banners WHERE Id = :Id');
        $statement->execute([':Id' => $id]);
        jsonResponse(['message' => 'Banner deleted successfully']);
    }

    if (preg_match('#^/api/banners/(\d+)/toggle$#', $path, $matches) === 1 && $method === 'PUT') {
        requireAdmin();
        $id = (int) $matches[1];
        $banner = fetchBannerById($id);
        if ($banner === null) {
            jsonResponse(['message' => 'Banner not found'], 404);
        }

        $statement = db()->prepare(
            'UPDATE Banners SET IsActive = :IsActive, UpdatedAt = :UpdatedAt WHERE Id = :Id'
        );
        $statement->execute([
            ':Id' => $id,
            ':IsActive' => !toBool($banner['IsActive']) ? 1 : 0,
            ':UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);
        jsonResponse(fetchBannerById($id));
    }
}
