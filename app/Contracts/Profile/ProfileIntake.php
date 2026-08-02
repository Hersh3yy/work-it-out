<?php

declare(strict_types=1);

namespace App\Contracts\Profile;

use App\Models\User;
use App\Services\Profile\ProfileIntakeReport;

/**
 * Port for assessing how complete a user's training profile is and which
 * questions a coach should ask next to fill the gaps.
 */
interface ProfileIntake
{
    public function report(User $user): ProfileIntakeReport;
}
