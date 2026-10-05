<?php

declare(strict_types=1);

namespace AM\SkyMineZ\event;

use pocketmine\event\Event;

/**
 * Base class for every event SkyMineZ raises.
 *
 * Other plugins listen to these instead of poking the managers directly:
 *
 *     $pluginManager->registerEvent(
 *         OutpostCaptureEvent::class,
 *         function(OutpostCaptureEvent $event): void {
 *             $event->setCancelled();
 *         },
 *         EventPriority::NORMAL,
 *         $yourPlugin
 *     );
 *
 * Call {@link Event::hasHandlers()} before building one of these in hot code
 * paths; constructing the object for nobody is pure overhead.
 */
abstract class SkyMineEvent extends Event
{
}