<?php

declare(strict_types=1);

/**
 * @project Castor Container
 * @link https://github.com/castor-labs/container
 * @package castor/container
 * @author Matias Navarro-Carter mnavarrocarter@gmail.com
 * @license MIT
 * @copyright 2021 CastorLabs Ltd
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Castor;

use Psr\Container\ContainerExceptionInterface;

/**
 * Class ResolutionError.
 */
class ContainerError extends \RuntimeException implements ContainerExceptionInterface
{
}
