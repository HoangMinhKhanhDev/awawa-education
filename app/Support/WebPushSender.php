<?php

namespace App\Support;

use App\Models\PushSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('awawa.webpush.public_key')) && filled(config('awawa.webpush.private_key'));
    }

    public function publicKey(): ?string
    {
        return config('awawa.webpush.public_key');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(User $user, array $payload): void
    {
        if (! $this->configured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('awawa.webpush.subject') ?: config('app.url'),
                    'publicKey' => config('awawa.webpush.public_key'),
                    'privateKey' => config('awawa.webpush.private_key'),
                ],
            ]);
        } catch (\Throwable) {
            return;
        }

        $message = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification($this->toMinishlink($subscription), $message);
        }

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $report->getEndpoint())->delete();
            }
        }
    }

    protected function toMinishlink(PushSubscription $subscription): Subscription
    {
        return Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding ?: 'aesgcm',
        ]);
    }
}
