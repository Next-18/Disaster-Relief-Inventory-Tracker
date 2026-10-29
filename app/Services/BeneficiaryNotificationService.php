<?php

namespace App\Services;

use App\Models\BeneficiaryNotification;
use App\Models\Distribution;
use App\Models\User;

class BeneficiaryNotificationService
{
    public function notifyPendingAssignmentExpired(Distribution $distribution): void
    {
        $distribution->loadMissing(['beneficiary', 'reliefPackage']);
        $user = User::query()
            ->where('beneficiary_id', $distribution->beneficiary_id)
            ->first();

        if (! $user) {
            return;
        }

        $packageName = $distribution->reliefPackage?->package_name ?? 'relief goods';
        $deadline = $distribution->deadline?->format('M j, Y') ?? 'the scheduled deadline';

        BeneficiaryNotification::query()->create([
            'user_id' => $user->id,
            'distribution_id' => null,
            'title' => 'Relief collection deadline passed',
            'message' => "The collection deadline for {$packageName} was {$deadline}. This assignment has expired. Contact your Barangay Administrator if you need help.",
        ]);
    }

    public function notifyPendingAssignmentCancelled(Distribution $distribution): void
    {
        if ($distribution->status !== 'Pending') {
            return;
        }

        $distribution->loadMissing(['beneficiary', 'reliefPackage']);
        $user = User::query()
            ->where('beneficiary_id', $distribution->beneficiary_id)
            ->first();

        if (! $user) {
            return;
        }

        $packageName = $distribution->reliefPackage?->package_name ?? 'relief goods';

        BeneficiaryNotification::query()->create([
            'user_id' => $user->id,
            // Keep the cancellation notice after the distribution is deleted.
            'distribution_id' => null,
            'title' => 'Relief goods assignment cancelled',
            'message' => "Your assignment to receive {$packageName} has been cancelled and is no longer available. Please contact your Barangay Administrator if you need help.",
        ]);
    }

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
            if ($distribution->start_date && $distribution->deadline) {
                $message .= ' Collection is open from '.$distribution->start_date->format('M j, Y').' through '.$distribution->deadline->format('M j, Y').'.';
            } elseif ($distribution->start_date) {
                $message .= ' Collection starts on '.$distribution->start_date->format('M j, Y').'.';
            } elseif ($distribution->deadline) {
                $message .= ' Please collect it by '.$distribution->deadline->format('M j, Y').'.';
            } elseif ($distribution->date_released) {
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
