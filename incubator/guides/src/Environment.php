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

namespace phpDocumentor\Guides;

use League\Flysystem\FilesystemInterface;
use phpDocumentor\Guides\Meta\Entry;
use phpDocumentor\Guides\Nodes\SpanNode;
use Psr\Log\LoggerInterface;

use function array_shift;
use function dirname;
use function strtolower;
use function trim;

class Environment
{
    /** @var UrlGenerator */
    private $urlGenerator;

    /** @var int */
    private $initialHeaderLevel;

    /** @var int */
    private $currentTitleLevel = 0;

    /** @var string[] */
    private $titleLetters = [];

    /** @var string */
    private $currentFileName = '';

    /** @var FilesystemInterface */
    private $origin;

    /** @var string */
    private $currentDirectory = '.';

    /** @var Metas */
    private $metas;

    /** @var string[] */
    private $variables = [];

    /** @var string[] */
    private $links = [];

    /** @var string[] */
    private $anonymous = [];

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $destinationPath;

    /** @var string */
    private $currentAbsolutePath = '';

    public function __construct(
        string $outputFolder,
        int $initialHeaderLevel,
        LoggerInterface $logger,
        FilesystemInterface $origin,
        Metas $metas,
        UrlGenerator $urlGenerator
    ) {
        $this->destinationPath = $outputFolder;
        $this->initialHeaderLevel = $initialHeaderLevel;
        $this->origin = $origin;
        $this->logger = $logger;
        $this->urlGenerator = $urlGenerator;
        $this->metas = $metas;

        $this->reset();
    }

    //Parser
    public function reset(): void
    {
        $this->titleLetters = [];
        $this->currentTitleLevel = 0;
    }

    //Parser
    public function getInitialHeaderLevel(): int
    {
        return $this->initialHeaderLevel;
    }

    //Parser to populate, renderer to restore

    /**
     * @param mixed $value
     */
    public function setVariable(string $variable, $value): void
    {
        $this->variables[$variable] = $value;
    }

    //Renderer

    /**
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getVariable(string $variable, $default = null)
    {
        return $this->variables[$variable] ?? $default;
    }

    //Parser to stash after parsing completed

    /**
     * @return array<string|SpanNode>
     */
    public function getVariables(): array
    {
        return $this->variables;
    }

    //Parser
    public function setLink(string $name, string $url): void
    {
        $name = strtolower(trim($name));

        if ($name === '_') {
            $name = array_shift($this->anonymous);
        }

        $this->links[$name] = trim($url);
    }

    //Parser (span)
    public function resetAnonymousStack(): void
    {
        $this->anonymous = [];
    }

    //Parser (span)
    public function pushAnonymous(string $name): void
    {
        $this->anonymous[] = strtolower(trim($name));
    }

    //Parser to stash after parsing completed

    /**
     * @return string[]
     */
    public function getLinks(): array
    {
        return $this->links;
    }

    //Renderer
    public function getLink(string $name, bool $relative = true): string
    {
        $name = strtolower(trim($name));

        if (isset($this->links[$name])) {
            $link = $this->links[$name];

            if ($relative) {
                return $this->relativeUrl($link);
            }

            return $link;
        }

        return '';
    }

    //Parser (assets) & renderer
    public function relativeUrl(?string $url): string
    {
        return $this->urlGenerator->relativeUrl($url);
    }

    //TocTree builder
    public function absoluteUrl(string $url): string
    {
        return $this->urlGenerator->absoluteUrl($this->getDirName(), $url);
    }

    //Toc, Resolver
    public function canonicalUrl(string $url): ?string
    {
        return $this->urlGenerator->canonicalUrl($this->getDirName(), $url);
    }

    //Assets extension (rendering)
    public function outputUrl(string $url): ?string
    {
        return $this->urlGenerator->absoluteUrl(
            $this->destinationPath,
            $this->canonicalUrl($url)
        );
    }

    //Renderer
    public function generateUrl(string $path): string
    {
        return $this->urlGenerator->generateUrl($path, $this->getDirName());
    }

    //Parser, Toc
    public function absoluteRelativePath(string $url): string
    {
        return $this->currentDirectory . '/' . $this->getDirName() . '/' . $this->relativeUrl($url);
    }

    //Toc
    public function getDirName(): string
    {
        $dirname = dirname($this->currentFileName);

        if ($dirname === '.') {
            return '';
        }

        return $dirname;
    }

    //Parser, Renderer
    public function setCurrentFileName(string $filename): void
    {
        $this->currentFileName = $filename;
    }

    //Parser
    public function getCurrentFileName(): string
    {
        return $this->currentFileName;
    }

    //Parser, Renderer
    public function getOrigin(): FilesystemInterface
    {
        return $this->origin;
    }

    //Parser, Renderer
    public function setCurrentDirectory(string $directory): void
    {
        $this->currentDirectory = $directory;
    }

    //Parser
    public function getCurrentDirectory(): string
    {
        return $this->currentDirectory;
    }

    //Parser, (metas)
    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }

    //Parser, Renderer
    public function getUrl(): string
    {
        return $this->currentFileName;
    }

    //Resolver
    public function getMetas(): Metas
    {
        return $this->metas;
    }

    //Renderer
    public function getMetaEntry(): ?Entry
    {
        return $this->metas->get($this->currentFileName);
    }

    //Parser
    public function getLevel(string $letter): int
    {
        foreach ($this->titleLetters as $level => $titleLetter) {
            if ($letter === $titleLetter) {
                return $level;
            }
        }

        $this->currentTitleLevel++;
        $this->titleLetters[$this->currentTitleLevel] = $letter;

        return $this->currentTitleLevel;
    }

    public function addError(string $message): void
    {
        $this->logger->error($message);
    }

    //Parser, Renderer

    /**
     * Register the current file's absolute path on the Origin file system.
     *
     * You would more or less expect getCurrentFileName to return this information; but that filename does
     * not return the absolute position on Origin but the relative path from the Documentation Root.
     */
    public function setCurrentAbsolutePath(string $path): void
    {
        $this->currentAbsolutePath = $path;
    }

    //Parser, Renderer

    /**
     * Return the current file's absolute path on the Origin file system.
     *
     * In order to load files relative to the current file (such as embedding UML diagrams) the environment
     * must expose what the absolute path relative to the Origin is.
     *
     * @see self::setCurrentAbsolutePath() for more information
     * @see self::getOrigin() for the filesystem on which to use this path
     */
    public function getCurrentAbsolutePath(): string
    {
        return $this->currentAbsolutePath;
    }
}
