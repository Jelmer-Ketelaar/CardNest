<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Scheduler;

use App\Pricing\Application\Message\QueueDailyPriceRefresh;
use DateTimeZone;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('pricing')]
final class PricingScheduleProvider implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LockFactory $lockFactory,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(RecurringMessage::cron(
                '0 12 * * *',
                new RedispatchMessage(new QueueDailyPriceRefresh(), 'async'),
                new DateTimeZone('Europe/Amsterdam'),
            ))
            ->lock($this->lockFactory->createLock('pricing-schedule'))
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
