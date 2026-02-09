<?php

namespace App\Services;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmService
{
    /**
     * Send a push notification to a specific device token.
     *
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendNotification($token, $title, $body, $data = [])
    {
        try {
            $messaging = app('firebase.messaging');
            
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);

            $result = $messaging->send($message);
            \Illuminate\Support\Facades\Log::info('FCM Send Success', ['token' => $token, 'result' => $result]);
            return $result;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('FCM Send Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Send a push notification to a specific user.
     *
     * @param \App\Models\User $user
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array|null
     */
    public function sendToUser($user, $title, $body, $data = [])
    {
        if (!$user->fcm_token) {
            return null;
        }

        return $this->sendNotification($user->fcm_token, $title, $body, $data);
    }

    /**
     * Send a push notification to all users with a specific role.
     *
     * @param string $role
     * @param string|null $schoolId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToRole($role, $schoolId, $title, $body, $data = [])
    {
        $users = \App\Models\User::role($role)
            ->when($schoolId, function ($query) use ($schoolId) {
                return $query->where('school_id', $schoolId);
            })
            ->whereNotNull('fcm_token')
            ->get();

        $results = [];
        foreach ($users as $user) {
            $results[] = $this->sendToUser($user, $title, $body, $data);
        }

        return $results;
    }
}
