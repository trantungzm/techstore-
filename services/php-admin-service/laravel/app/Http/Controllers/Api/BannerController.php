<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;

class BannerController extends BaseController
{
    public function active()
    {
        $rows = DB::select(<<<'SQL'
SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
FROM Banners
WHERE IsActive = 1
ORDER BY DisplayOrder, Id
SQL
        );

        return response()->json($rows);
    }

    public function index(Request $request)
    {
        // admin check
        $claims = $request->attributes->get('claims', []);
        $roles = $claims['roles'] ?? ($claims['role'] ?? null);
        if (!self::hasAdminRole($roles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = DB::select(<<<'SQL'
SELECT Id, Kicker, Title, SubTitle, CtaLabel, CtaTo, ImageUrl, OfferTitle, OfferDiscount, OfferProduct, DisplayOrder, IsActive, CreatedAt, UpdatedAt
FROM Banners
ORDER BY DisplayOrder, Id
SQL
        );

        return response()->json($rows);
    }

    public function store(Request $request)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $payload = $request->json()->all();
        // basic validation — keep minimal, better to use FormRequest
        $now = gmdate('Y-m-d H:i:s');
        $id = DB::table('Banners')->insertGetId([
            'Kicker' => $payload['kicker'] ?? '',
            'Title' => $payload['title'] ?? '',
            'SubTitle' => $payload['subTitle'] ?? '',
            'CtaLabel' => $payload['ctaLabel'] ?? '',
            'CtaTo' => $payload['ctaTo'] ?? '',
            'ImageUrl' => $payload['imageUrl'] ?? '',
            'OfferTitle' => $payload['offerTitle'] ?? '',
            'OfferDiscount' => $payload['offerDiscount'] ?? '',
            'OfferProduct' => $payload['offerProduct'] ?? '',
            'DisplayOrder' => $payload['displayOrder'] ?? 0,
            'IsActive' => isset($payload['isActive']) ? ($payload['isActive'] ? 1 : 0) : 1,
            'CreatedAt' => $now,
            'UpdatedAt' => null,
        ]);

        $banner = DB::selectOne('SELECT * FROM Banners WHERE Id = ?', [$id]);
        return response()->json($banner, 201);
    }

    public function show(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $banner = DB::selectOne('SELECT * FROM Banners WHERE Id = ?', [$id]);
        if (!$banner) {
            return response()->json(['message' => 'Banner not found'], 404);
        }
        return response()->json($banner);
    }

    public function update(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exists = DB::selectOne('SELECT Id FROM Banners WHERE Id = ?', [$id]);
        if (!$exists) {
            return response()->json(['message' => 'Banner not found'], 404);
        }

        $payload = $request->json()->all();
        DB::table('Banners')->where('Id', $id)->update([
            'Kicker' => $payload['kicker'] ?? '',
            'Title' => $payload['title'] ?? '',
            'SubTitle' => $payload['subTitle'] ?? '',
            'CtaLabel' => $payload['ctaLabel'] ?? '',
            'CtaTo' => $payload['ctaTo'] ?? '',
            'ImageUrl' => $payload['imageUrl'] ?? '',
            'OfferTitle' => $payload['offerTitle'] ?? '',
            'OfferDiscount' => $payload['offerDiscount'] ?? '',
            'OfferProduct' => $payload['offerProduct'] ?? '',
            'DisplayOrder' => $payload['displayOrder'] ?? 0,
            'IsActive' => isset($payload['isActive']) ? ($payload['isActive'] ? 1 : 0) : 1,
            'UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $banner = DB::selectOne('SELECT * FROM Banners WHERE Id = ?', [$id]);
        return response()->json($banner);
    }

    public function destroy(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $exists = DB::selectOne('SELECT Id FROM Banners WHERE Id = ?', [$id]);
        if (!$exists) {
            return response()->json(['message' => 'Banner not found'], 404);
        }

        DB::table('Banners')->where('Id', $id)->delete();
        return response()->json(['message' => 'Banner deleted successfully']);
    }

    public function toggle(Request $request, $id)
    {
        $claims = $request->attributes->get('claims', []);
        if (!self::hasAdminRole($claims['roles'] ?? ($claims['role'] ?? null))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $banner = DB::selectOne('SELECT * FROM Banners WHERE Id = ?', [$id]);
        if (!$banner) {
            return response()->json(['message' => 'Banner not found'], 404);
        }

        $isActive = !$banner->IsActive;
        DB::table('Banners')->where('Id', $id)->update([
            'IsActive' => $isActive ? 1 : 0,
            'UpdatedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $banner = DB::selectOne('SELECT * FROM Banners WHERE Id = ?', [$id]);
        return response()->json($banner);
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
