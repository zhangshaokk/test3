<?php

declare(strict_types=1);

/**
 * This file is part of phpDocumentor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @link https://phpdoc.org
 */

namespace phpDocumentor\Event\Mock;

use phpDocumentor\Event\EventAbstract as BaseClass;

/**
 * EventAbstract Mocking Class.
 *
 * We need a real mock because events may be constructed using a static factory method. But we cannot invoke
 * those on a mock constructed with Mockery, phpUnit or directly on the abstract class.
 */
class EventAbstract extends BaseClass
{
    /**
     * Creates a new instance of a derived object and return that.
     *
     * Used as convenience method for fluent interfaces.
     */
    public static function createInstance(object $subject): BaseClass
    {
        return new static($subject);
    }
}
