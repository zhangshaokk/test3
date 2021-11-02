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

namespace phpDocumentor\Extension;

use DirectoryIterator;
use Generator;
use Jean85\PrettyVersions;
use PharIo\Manifest\ApplicationName;
use phpDocumentor\AutoloaderLocator;
use Symfony\Component\Config\ResourceCheckerConfigCache;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_filter;
use function count;
use function file_exists;

final class ExtensionHandler implements EventSubscriberInterface
{
    /** @var ExtensionHandler|null */
    private static $instance;

    /** @var string */
    private $cacheDir;

    /** @var Extension[] */
    private $extensions;

    /** @var ?ResourceCheckerConfigCache */
    private $cache;

    /** @var string */
    private $extensionsDir;

    /** @var ExtensionLoader[] */
    private $loaders = [];

    /** @var Extension[] */
    private $invalidExtensions;

    /** @var Validator */
    private $validator;

    private function __construct(string $cacheDir, string $extensionsDir)
    {
        $this->cacheDir = $cacheDir;
        $this->extensionsDir = $extensionsDir;
        $this->loaders[] = new DirectoryLoader();
        $this->validator = new Validator(
            new ApplicationName(PrettyVersions::getRootPackageName())
        );
    }

    public static function getInstance(string $cacheDir, string $extensionsDir): self
    {
        if (self::$instance instanceof self === false) {
            self::$instance = new self($cacheDir, $extensionsDir);
        }

        return self::$instance;
    }

    public function isFresh(): bool
    {
        $extensionConfig = $this->getCache();
        $fresh = $extensionConfig->isFresh();
        if ($fresh === false) {
            $this->refresh();
        }

        return $fresh;
    }

    public function refresh(): void
    {
        $this->cache->write('', [new ExtensionsResource($this->extensions)]);
    }

    private function getCache(): ResourceCheckerConfigCache
    {
        if ($this->cache instanceof ResourceCheckerConfigCache) {
            return $this->cache;
        }

        $this->cache = new ResourceCheckerConfigCache(
            $this->cacheDir,
            [new ExtensionLockChecker($this->getExtensions())]
        );

        return $this->cache;
    }

    /** @return Extension[] */
    private function getExtensions(): array
    {
        if ($this->extensions !== null) {
            return $this->extensions;
        }

        if (file_exists($this->extensionsDir) === false) {
            $this->extensions = [];

            return $this->extensions;
        }

        $extensions = [];
        $iterator = new DirectoryIterator($this->extensionsDir);
        foreach ($iterator as $dir) {
            if ($dir->isDot()) {
                continue;
            }

            foreach ($this->loaders as $loader) {
                if (!$loader->supports($dir)) {
                    continue;
                }

                $extensions[$dir->getPathName()] = $loader->load(new DirectoryIterator($dir->getPathName()));
            }
        }

        $extensions = array_filter($extensions);
        $this->extensions = array_filter($extensions, function (Extension $extension) {
            return $this->validator->isValid($extension->getManifest());
        });

        $this->invalidExtensions = array_filter($extensions, function (Extension $extension) {
            return $this->validator->isValid($extension->getManifest()) === false;
        });

        return $this->extensions;
    }

    /** @return Generator<class-string> */
    public function loadExtensions(): Generator
    {
        foreach ($this->getExtensions() as $extension) {
            $namespace = $extension->getNamespace();
            AutoloaderLocator::loader()->addPsr4($namespace, [$extension->getPath()]);

            yield $extension->getExtensionClass();
        }
    }

    public function onBoot(ConsoleCommandEvent $event): void
    {
        $io = new SymfonyStyle($event->getInput(), $event->getOutput());
        $extensions = $this->getExtensions();
        if (count($extensions) > 0) {
            $io->writeln('Loaded extensions:');
            foreach ($extensions as $extension) {
                $io->success($extension->getName() . ':' . $extension->getVersion());
            }
        }

        if (count($this->invalidExtensions) <= 0) {
            return;
        }

        $io->writeln('Failed to load extensions:');
        foreach ($this->invalidExtensions as $extension) {
            $io->warning($extension->getName() . ':' . $extension->getVersion());
        }
    }

    /** @return array<string, mixed> */
    public static function getSubscribedEvents(): array
    {
        return ['console.command' => 'onBoot'];
    }
}
