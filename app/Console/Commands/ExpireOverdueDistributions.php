<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Distribution;
use App\Services\BeneficiaryNotificationService;
use App\Services\DistributionStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireOverdueDistributions extends Command
{
    protected $signature = 'distributions:expire-overdue';

    protected $description = 'Cancel expired pending distributions and restore their reserved stock';

    public function handle(DistributionStockService $stock, BeneficiaryNotificationService $notifications): int
    {
        $expiredCount = 0;

        Distribution::query()
            ->where('status', 'Pending')
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<', today()->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($candidates) use ($stock, $notifications, &$expiredCount) {
                foreach ($candidates as $candidate) {
                    $expired = DB::transaction(function () use ($candidate, $stock, $notifications) {
                        $distribution = Distribution::query()
                            ->with('inventoryItems')
                            ->lockForUpdate()
                            ->find($candidate->id);

                        if (! $distribution || $distribution->status !== 'Pending'
                            || ! $distribution->deadline
                            || $distribution->deadline->toDateString() >= today()->toDateString()) {
                            return false;
                        }

                        $stock->restoreFor($distribution);
                        $distribution->status = 'Cancelled';
                        $distribution->save();
                        $notifications->notifyPendingAssignmentExpired($distribution);

                        AuditLog::query()->create([
                            'user_id' => $distribution->distributed_by,
                            'action' => 'expire',
                            'module' => 'distribution',
                            'description' => "Expired pending {$distribution->reliefPackage?->package_name} assignment ID {$distribution->id}; reserved stock restored.",
                        ]);

                        return true;
                    });

                    if ($expired) {
                        $expiredCount++;
                    }
                }
            });

        $this->info("Expired {$expiredCount} pending distribution assignment(s).");

        return self::SUCCESS;
    }
}
