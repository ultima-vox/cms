<?php

declare(strict_types=1);

namespace Core\Page;

use RuntimeException;

/** Signals that an otherwise valid page has no publicly visible domain resource. */
final class PageNotFoundException extends RuntimeException
{
}
