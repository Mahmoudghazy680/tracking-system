<?php

namespace App\Http\Controllers\Api;

use App\Models\MonitoredEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailMonitoringController
{
    /**
     * Store an email event reported by the desktop client.
     *
     * POST /api/email-events
     *
     * Expected payload (all fields optional except none):
     * {
     *   "email_client":     "outlook",
     *   "direction":        "sent|received|unknown",
     *   "from_address":     "user@example.com",
     *   "to_addresses":     ["a@b.com", "c@d.com"],
     *   "subject":          "Hello",
     *   "body_excerpt":     "First 500 chars...",
     *   "has_attachment":   true,
     *   "email_datetime":   "2026-05-05T10:00:00Z",
     *   "time_interval_id": 123
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email_client'     => 'nullable|string|max:64',
            'direction'        => 'nullable|in:sent,received,unknown',
            'from_address'     => 'nullable|string|max:255',
            'to_addresses'     => 'nullable|array',
            'to_addresses.*'   => 'string|max:255',
            'subject'          => 'nullable|string|max:998',
            'body_excerpt'     => 'nullable|string|max:2000',
            'has_attachment'   => 'nullable|boolean',
            'email_datetime'   => 'nullable|date',
            'time_interval_id' => 'nullable|integer|exists:time_intervals,id',
        ]);

        $email = MonitoredEmail::create([
            'user_id'          => $request->user()->id,
            'email_client'     => $validated['email_client'] ?? 'unknown',
            'direction'        => $validated['direction'] ?? 'unknown',
            'from_address'     => $validated['from_address'] ?? null,
            'to_addresses'     => $validated['to_addresses'] ?? null,
            'subject'          => $validated['subject'] ?? null,
            'body_excerpt'     => $validated['body_excerpt'] ?? null,
            'has_attachment'   => $validated['has_attachment'] ?? false,
            'email_datetime'   => $validated['email_datetime'] ?? now(),
            'time_interval_id' => $validated['time_interval_id'] ?? null,
        ]);

        return responder()->success(['id' => $email->id])->respond(201);
    }
}
