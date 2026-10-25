<?php

namespace App\Services;

use App\Models\BeneficiaryNotification;
use App\Models\Distribution;
use App\Models\User;

class BeneficiaryNotificationService
{
    /** @param iterable<Distribution> $distributions */
    public function notifyPendingAssignments(iterable $distributions): void
    {
        $pending = collect($distributions)
            ->filter(fn (Distribution $distribution) => $distribution->status === 'Pending')
            ->values();
        if ($pending->isEmpty()) {
            return;
        }

        $users = User::query()
            ->whereIn('beneficiary_id', $pending->pluck('beneficiary_id')->unique())
            ->get()
            ->keyBy('beneficiary_id');
        $now = now();
        $rows = [];

        foreach ($pending as $distribution) {
            $distribution->loadMissing(['beneficiary', 'reliefPackage']);
            $user = $users->get($distribution->beneficiary_id);
            if (! $user) {
                continue;
            }

            $packageName = $distribution->reliefPackage?->package_name ?? 'relief goods';
            $message = "You have been selected to receive {$packageName}.";
            if ($distribution->date_released) {
                $message .= ' Please collect it on '.$distribution->date_released->format('M j, Y').'.';
            }
            $message .= ' Show your QR code to relief staff when collecting it.';

            $rows[] = [
                'user_id' => $user->id,
                'distribution_id' => $distribution->id,
                'title' => 'Relief goods assigned to you',
                'message' => $message,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            BeneficiaryNotification::query()->insertOrIgnore($rows);
        }
    }
}
