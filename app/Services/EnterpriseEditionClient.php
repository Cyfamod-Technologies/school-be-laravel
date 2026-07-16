<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * This backend's side of the one integration point with the private
 * sms-enterprise-edition service -- sends Go Live requests/resends there,
 * and checks status from there. Never called from a browser directly;
 * the frontend calls this backend's own authenticated endpoints, which
 * call this client server-to-server.
 */
class EnterpriseEditionClient
{
    private function client()
    {
        return Http::withHeaders([
            'X-Internal-Secret' => config('services.internal_shared_secret'),
        ])->baseUrl(rtrim(config('services.enterprise_edition.base_url'), '/'));
    }

    public function requestGoLive(string $schoolId, string $schoolName, string $schoolDomain): Response
    {
        return $this->client()->post('/api/go-live', [
            'school_id' => $schoolId,
            'school_name' => $schoolName,
            'school_domain' => $schoolDomain,
        ]);
    }

    public function goLiveStatus(string $schoolId): Response
    {
        return $this->client()->get("/api/go-live/{$schoolId}");
    }
}
