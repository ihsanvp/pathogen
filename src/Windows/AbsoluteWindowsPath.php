<?php

/*
 * This file is part of the Pathogen package.
 *
 * Copyright © 2014 Erin Millard
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Eloquent\Pathogen\Windows;

use Eloquent\Pathogen\AbsolutePath;
use Eloquent\Pathogen\AbsolutePathInterface;
use Eloquent\Pathogen\Exception\EmptyPathAtomException;
use Eloquent\Pathogen\Exception\EmptyPathException;
use Eloquent\Pathogen\Exception\InvalidPathAtomCharacterException;
use Eloquent\Pathogen\Exception\InvalidPathAtomExceptionInterface;
use Eloquent\Pathogen\Exception\PathAtomContainsSeparatorException;
use Eloquent\Pathogen\FileSystem\AbsoluteFileSystemPathInterface;
use Eloquent\Pathogen\Normalizer\PathNormalizerInterface;
use Eloquent\Pathogen\PathInterface;
use Eloquent\Pathogen\RelativePathInterface;
use Eloquent\Pathogen\Resolver\BasePathResolverInterface;
use Eloquent\Pathogen\Windows\Exception\DriveMismatchException;
use Eloquent\Pathogen\Windows\Exception\InvalidDriveSpecifierException;

/**
 * Represents an absolute Windows path.
 */
class AbsoluteWindowsPath extends AbsolutePath implements
    AbsoluteFileSystemPathInterface,
    AbsoluteWindowsPathInterface
{
    /**
     * Creates a new absolute Windows path from a set of path atoms and a drive
     * specifier.
     *
     * @param string        $drive                The drive specifier.
     * @param mixed<string> $atoms                The path atoms.
     * @param boolean|null  $hasTrailingSeparator True if the path has a trailing separator.
     *
     * @return AbsoluteWindowsPathInterface      The newly created path instance.
     * @throws InvalidPathAtomExceptionInterface If any of the supplied atoms are invalid.
     */
    public static function fromDriveAndAtoms(
        $drive,
        $atoms,
        $hasTrailingSeparator = null
    ) {
        return static::factory()->createFromDriveAndAtoms(
            $atoms,
            $drive,
            true,
            false,
            $hasTrailingSeparator
        );
    }

    /**
     * Construct a new absolute Windows path instance (internal use only).
     *
     * @internal This method is not intended for public use.
     *
     * @param string        $drive                The drive specifier, or null if the path has no drive specifier.
     * @param mixed<string> $atoms                The path atoms.
     * @param boolean|null  $hasTrailingSeparator True if this path has a trailing separator.
     *
     * @throws InvalidDriveSpecifierException    If the drive specifier is invalid.
     * @throws InvalidPathAtomExceptionInterface If any of the supplied path atoms are invalid.
     */
    public static function constructWindowsPath(
        $drive,
        $atoms,
        $hasTrailingSeparator = null
    ) {
        static::validateDriveSpecifier($drive);

        return new static(
            $drive,
            static::normalizeAtoms($atoms),
            $hasTrailingSeparator
        );
    }

    /**
     * Construct a new absolute Windows path instance without validation
     * (internal use only).
     *
     * @internal This method is not intended for public use.
     *
     * @param string        $drive                The drive specifier, or null if the path has no drive specifier.
     * @param mixed<string> $atoms                The path atoms.
     * @param boolean|null  $hasTrailingSeparator True if this path has a trailing separator.
     *
     * @throws InvalidDriveSpecifierException    If the drive specifier is invalid.
     * @throws InvalidPathAtomExceptionInterface If any of the supplied path atoms are invalid.
     */
    public static function constructWindowsPathUnsafe(
        $drive,
        $atoms,
        $hasTrailingSeparator = null
    ) {
        return new static($drive, $atoms, $hasTrailingSeparator);
    }

    // Implementation of WindowsPathInterface ==================================

    /**
     * Get this path's drive specifier.
     *
     * Absolute Windows paths always have a drive specifier, and will never
     * return null for this method.
     *
     * @return string|null The drive specifier, or null if this path does not have a drive specifier.
     */
    public function drive()
    {
        return $this->drive;
    }

    /**
     * Determine whether this path has a drive specifier.
     *
     * Absolute Windows paths always have a drive specifier, and will always
     * return true for this method.
     *
     * @return boolean True is this path has a drive specifier.
     */
    public function hasDrive()
    {
        return true;
    }

    /**
     * Returns true if this path's drive specifier is equal to the supplied
     * drive specifier.
     *
     * This method is not case sensitive.
     *
     * @param string|null $drive The driver specifier to compare to.
     *
     * @return boolean True if the drive specifiers are equal.
     */
    public function matchesDrive($drive)
    {
        return $this->driveSpecifiersMatch($this->drive(), $drive);
    }

    /**
     * Returns true if this path's drive specifier matches the supplied drive
     * specifier, or if either drive specifier is null.
     *
     * This method is not case sensitive.
     *
     * @param string|null $drive The driver specifier to compare to.
     *
     * @return boolean True if the drive specifiers match, or either drive specifier is null.
     */
    public function matchesDriveOrNull($drive)
    {
        return null === $drive || $this->matchesDrive($drive);
    }

    /**
     * Joins the supplied drive specifier to this path.
     *
     * @return string|null $drive The drive specifier to use, or null to remove the drive specifier.
     *
     * @return WindowsPathInterface A new path instance with the supplied drive specifier joined to this path.
     */
    public function joinDrive($drive)
    {
        if (null === $drive) {
            return $this->createPathFromDriveAndAtoms(
                $this->atoms(),
                null,
                false,
                true,
                false
            );
        }

        return $this->createPathFromDriveAndAtoms(
            $this->atoms(),
            $drive,
            true,
            false,
            false
        );
    }

    // Implementation of AbsolutePathInterface =================================

    /**
     * Determine if this path is the direct parent of the supplied path.
     *
     * @param AbsolutePathInterface $path The child path.
     *
     * @return boolean True if this path is the direct parent of the supplied path.
     */
    public function isParentOf(AbsolutePathInterface $path)
    {
        if (!$this->matchesDriveOrNull($this->pathDriveSpecifier($path))) {
            return false;
        }

        return parent::isParentOf($path);
    }

    /**
     * Determine if this path is an ancestor of the supplied path.
     *
     * @param AbsolutePathInterface $path The child path.
     *
     * @return boolean True if this path is an ancestor of the supplied path.
     */
    public function isAncestorOf(AbsolutePathInterface $path)
    {
        if (!$this->matchesDriveOrNull($this->pathDriveSpecifier($path))) {
            return false;
        }

        return parent::isAncestorOf($path);
    }

    /**
     * Determine the shortest path from the supplied path to this path.
     *
     * For example, given path A equal to '/foo/bar', and path B equal to
     * '/foo/baz', A relative to B would be '../bar'.
     *
     * @param AbsolutePathInterface $path The path that the generated path will be relative to.
     *
     * @return RelativePathInterface A relative path from the supplied path to this path.
     */
    public function relativeTo(AbsolutePathInterface $path)
    {
        if (!$this->matchesDriveOrNull($this->pathDriveSpecifier($path))) {
            return $this->toRelative();
        }

        return parent::relativeTo($path);
    }

    // Implementation of PathInterface =========================================

    /**
     * Generate a string representation of this path.
     *
     * @return string A string representation of this path.
     */
    public function string()
    {
        return
            $this->drive() .
            ':' .
            static::ATOM_SEPARATOR .
            implode(static::ATOM_SEPARATOR, $this->atoms()) .
            ($this->hasTrailingSeparator() ? static::ATOM_SEPARATOR : '');
    }

    /**
     * Joins the supplied path to this path.
     *
     * @param RelativePathInterface $path The path whose atoms should be joined to this path.
     *
     * @return PathInterface          A new path with the supplied path suffixed to this path.
     * @throws DriveMismatchException If the supplied path has a drive that does not match this path's drive.
     */
    public function join(RelativePathInterface $path)
    {
        if ($path instanceof RelativeWindowsPathInterface) {
            if (!$this->matchesDriveOrNull($this->pathDriveSpecifier($path))) {
                throw new DriveMismatchException(
                    $this->drive(),
                    $path->drive()
                );
            }

            if ($path->isAnchored()) {
                return $path->joinDrive($this->drive());
            }
        }

        return parent::join($path);
    }

    /**
     * Get a relative version of this path.
     *
     * If this path is absolute, a new relative path with equivalent atoms will
     * be returned. Otherwise, this path will be retured unaltered.
     *
     * @return RelativePathInterface A relative version of this path.
     * @throws EmptyPathException    If this path has no atoms.
     */
    public function toRelative()
    {
        return $this->createPathFromDriveAndAtoms(
            $this->atoms(),
            $this->drive(),
            false,
            false,
            false
        );
    }

    // Implementation details ==================================================

    /**
     * Normalizes and validates a sequence of path atoms.
     *
     * This method is called internally by the constructor upon instantiation.
     * It can be overridden in child classes to change how path atoms are
     * normalized and/or validated.
     *
     * @param mixed<string> $atoms The path atoms to normalize.
     *
     * @return array<string>                      The normalized path atoms.
     * @throws EmptyPathAtomException             If any path atom is empty.
     * @throws PathAtomContainsSeparatorException If any path atom contains a separator.
     */
    protected static function normalizeAtoms($atoms)
    {
        foreach ($atoms as $atom) {
            if ('' === $atom) {
                throw new EmptyPathAtomException;
            } elseif (
                false !== strpos($atom, static::ATOM_SEPARATOR) ||
                false !== strpos($atom, '\\')
            ) {
                throw new PathAtomContainsSeparatorException($atom);
            } elseif (preg_match('/([\x00-\x1F<>:"|?*])/', $atom, $matches)) {
                throw new InvalidPathAtomCharacterException($atom, $matches[1]);
            }
        }

        return $atoms;
    }

    /**
     * Validates the suppled drive specifier.
     *
     * @param string $drive The drive specifier to validate.
     *
     * @throws InvalidDriveSpecifierException If the drive specifier is invalid.
     */
    protected static function validateDriveSpecifier($drive)
    {
        if (!preg_match('{^[a-zA-Z]$}', $drive)) {
            throw new InvalidDriveSpecifierException($drive);
        }
    }

    /**
     * Construct a new absolute Windows path instance (internal use only).
     *
     * @internal This method is not intended for public use.
     *
     * @param string        $drive                The drive specifier, or null if the path has no drive specifier.
     * @param mixed<string> $atoms                The path atoms.
     * @param boolean|null  $hasTrailingSeparator True if this path has a trailing separator.
     *
     * @throws InvalidDriveSpecifierException    If the drive specifier is invalid.
     * @throws InvalidPathAtomExceptionInterface If any of the supplied path atoms are invalid.
     */
    protected function __construct($drive, $atoms, $hasTrailingSeparator = null)
    {
        parent::__construct($atoms, $hasTrailingSeparator);

        $this->drive = $drive;
    }

    /**
     * Get the normalized form of the supplied drive specifier.
     *
     * @param string|null $drive The drive specifier to normalize.
     *
     * @return string|null The normalized drive specifier.
     */
    protected function normalizeDriveSpecifier($drive)
    {
        if (null === $drive) {
            return null;
        }

        return strtoupper($drive);
    }

    /**
     * Returns true if the supplied path specifiers match.
     *
     * @param string|null $left  The first specifier.
     * @param string|null $right The second specifier.
     *
     * @return boolean True if the drive specifiers match.
     */
    protected function driveSpecifiersMatch($left, $right)
    {
        return $this->normalizeDriveSpecifier($left) ===
            $this->normalizeDriveSpecifier($right);
    }

    /**
     * Get the the drive specifier of the supplied path, returning null if the
     * path is a non-Windows path.
     *
     * @param PathInterface $path The path.
     *
     * @return string|null The drive specifier.
     */
    protected function pathDriveSpecifier(PathInterface $path)
    {
        if ($path instanceof WindowsPathInterface) {
            return $path->drive();
        }

        return null;
    }

    /**
     * Creates a new path instance of the most appropriate type.
     *
     * This method is called internally every time a new path instance is
     * created as part of another method call. It can be overridden in child
     * classes to change which classes are used when creating new path
     * instances.
     *
     * @param mixed<string> $atoms                The path atoms.
     * @param boolean       $isAbsolute           True if the new path should be absolute.
     * @param boolean|null  $hasTrailingSeparator True if the new path should have a trailing separator.
     *
     * @return PathInterface The newly created path instance.
     */
    protected function createPath(
        $atoms,
        $isAbsolute,
        $hasTrailingSeparator = null
    ) {
        if ($isAbsolute) {
            return AbsoluteWindowsPath::constructWindowsPathUnsafe(
                $this->drive(),
                $atoms,
                $hasTrailingSeparator
            );
        }

        return RelativeWindowsPath::constructWindowsPathUnsafe(
            $atoms,
            null,
            false,
            $hasTrailingSeparator
        );
    }

    /**
     * Creates a new path instance of the most appropriate type from a set of
     * path atoms and a drive specifier.
     *
     * @param mixed<string> $atoms                The path atoms.
     * @param string|null   $drive                The drive specifier.
     * @param boolean|null  $isAbsolute           True if the path is absolute.
     * @param boolean|null  $isAnchored           True if the path is anchored to the drive root.
     * @param boolean|null  $hasTrailingSeparator True if the path has a trailing separator.
     *
     * @return WindowsPathInterface              The newly created path instance.
     * @throws InvalidPathAtomExceptionInterface If any of the supplied atoms are invalid.
     */
    protected function createPathFromDriveAndAtoms(
        $atoms,
        $drive,
        $isAbsolute = null,
        $isAnchored = null,
        $hasTrailingSeparator = null
    ) {
        if ($isAbsolute) {
            return AbsoluteWindowsPath::constructWindowsPathUnsafe(
                $drive,
                $atoms,
                $hasTrailingSeparator
            );
        }

        return RelativeWindowsPath::constructWindowsPathUnsafe(
            $atoms,
            $drive,
            $isAnchored,
            $hasTrailingSeparator
        );
    }

    /**
     * Get the most appropriate path factory for this type of path.
     *
     * @return Factory\WindowsPathFactoryInterface The path factory.
     */
    protected static function factory()
    {
        return Factory\WindowsPathFactory::instance();
    }

    /**
     * Get the most appropriate path normalizer for this type of path.
     *
     * @return PathNormalizerInterface The path normalizer.
     */
    protected static function normalizer()
    {
        return Normalizer\WindowsPathNormalizer::instance();
    }

    /**
     * Get the most appropriate base path resolver for this type of path.
     *
     * @return BasePathResolverInterface The base path resolver.
     */
    protected static function resolver()
    {
        return Resolver\WindowsBasePathResolver::instance();
    }

    private $drive;
}