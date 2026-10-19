<?php

namespace App\Shared\Application\Bus;

/**
 * A command or event that runs on the worker instead of in the request (imports, reminders, slow sends).
 */
interface AsyncMessage
{
}
