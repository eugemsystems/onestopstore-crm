<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class OrdersApi
{
    public function show(int $orderId): array
    {
        $res = Http::baseUrl(config('services.api.base_url'))
            ->withToken(config('services.api.token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 200)
            ->get("/api/crm-orders/{$orderId}");

        $res->throw();

        $js = $res->json();

        if (is_null($js)) {
            return [];
        }

        // Some endpoints may wrap payload under `data`
        if (is_array($js) && array_key_exists('data', $js) && is_array($js['data'])) {
            return $js['data'];
        }

        return is_array($js) ? $js : [];
    }

    public function list(array $params = []): array
    {
        $res = Http::baseUrl(config('services.api.base_url'))
            ->withToken(config('services.api.token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 200)
            ->get('/api/crm-orders', $params)
            ->throw();

        return $res->json();
    }

    public function update(int $orderId, array $data): array
    {
        $res = Http::baseUrl(config('services.api.base_url'))
            ->withToken(config('services.api.token'))
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 200)
            ->put("/api/crm-orders/{$orderId}", $data);

        $res->throw();
        return $res->json();
    }


    public function stream(int $perPage = 50, int $startPage = 1): \Generator
    {
        $page = $startPage;
        do {
            $payload = $this->list([
                'per_page' => $perPage,
                'page'     => $page,
            ]);

            $data = \Illuminate\Support\Arr::get($payload, 'data', []);
            foreach ($data as $order) {
                yield $order;
            }

            $next = \Illuminate\Support\Arr::get($payload, 'next_page_url');
            $page++;
        } while ($next);
    }


    public function streamSince(?string $updatedSinceIso8601 = null, int $perPage = 50): \Generator
    {
        $page = 1;
        do {
            $payload = $this->list([
                'updated_since' => $updatedSinceIso8601,
                'per_page'      => $perPage,
                'page'          => $page,
            ]);

            $data = Arr::get($payload, 'data', []);
            foreach ($data as $order) {
                yield $order;
            }

            $next = Arr::get($payload, 'next_page_url');
            $page++;
        } while ($next);
    }

    /**
     * Update item status for a specific order item
     * Note: This MUST be called with the external order_number (string). Do not pass numeric DB id.
     */
    public function updateItemStatus(string $orderNumber, array $data, ?string $actorEmail = null): array
    {
        $baseUrl = config('services.api.base_url');
        $url = rtrim($baseUrl, '/') . "/api/order/{$orderNumber}/item-status";


        try {
            $headers = ['X-From-CRM' => 'true'];
            if ($actorEmail) {
                $headers['X-CRM-Actor'] = $actorEmail;
            }

            $res = Http::baseUrl($baseUrl)
                ->withToken(config('services.api.token'))
                ->withHeaders($headers)
                ->acceptJson()
                ->timeout(15)
                ->retry(3, 200)
                ->put("/api/order/{$orderNumber}/item-status", $data);

            $statusCode = $res->status();
            $responseBody = $res->json();

            if (!$res->successful()) {
                \Log::error('OrdersApi::updateItemStatus failed', [
                    'order_number' => $orderNumber,
                    'http_status'  => $statusCode,
                    'response'     => $responseBody,
                    'url'          => $url,
                ]);
            }

            $res->throw();
            return $responseBody ?? [];
        } catch (\Exception $e) {
            \Log::error('OrdersApi::updateItemStatus exception', [
                'order_number' => $orderNumber,
                'error'        => $e->getMessage(),
                'url'          => $url,
            ]);
            throw $e;
        }
    }
    /**
     * Transfer an order item to inventory shipments on the API.
     * Creates a new inventory shipment record visible at /admin/inventory-shipments.
     */
    public function transferToInventory(array $data): array
    {
        $baseUrl = config('services.api.base_url');

        $res = Http::baseUrl($baseUrl)
            ->withToken(config('services.api.token'))
            ->withHeaders([
                'X-From-CRM' => 'true',
            ])
            ->acceptJson()
            ->timeout(15)
            ->retry(3, 200)
            ->put('/api/crm-inventory-shipments', $data);

        $res->throw();
        return $res->json();
    }

    /**
     * Update signed_by on an existing inventory shipment (after re-assignment from CRM).
     */
    public function updateShipmentSignedBy(int $shipmentId, ?int $signedBy): array
    {
        $baseUrl = config('services.api.base_url');

        $res = Http::baseUrl($baseUrl)
            ->withToken(config('services.api.token'))
            ->withHeaders(['X-From-CRM' => 'true'])
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 200)
            ->patch("/api/crm-inventory-shipments/{$shipmentId}/signed-by", [
                'signed_by' => $signedBy,
            ]);

        $res->throw();
        return $res->json();
    }
}
//api/order/33333/
