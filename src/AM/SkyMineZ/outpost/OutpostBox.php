<?php

declare(strict_types=1);

namespace AM\SkyMineZ\outpost;

use AM\SkyMineZ\useless\CollisionBox;

/**
 * The capture zone of an outpost.
 *
 * Outposts are cuboids like mines, so the geometry comes from
 * {@link CollisionBox}. Occupant scanning lives in {@link Outpost::tickCapture()},
 * which needs the furthest occupant rather than just a list, so there is no
 * separate helper here.
 */
class OutpostBox extends CollisionBox
{
}