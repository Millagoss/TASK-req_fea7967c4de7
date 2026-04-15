<?php

namespace Tests\Unit;

use App\Services\VersioningService;
use Tests\TestCase;

class VersioningServiceTest extends TestCase
{
    public function test_bump_version_major_returns_correct_result(): void
    {
        $this->assertEquals([2, 0, 0], VersioningService::bumpVersion('major', [1, 2, 3]));
    }

    public function test_bump_version_minor_returns_correct_result(): void
    {
        $this->assertEquals([1, 3, 0], VersioningService::bumpVersion('minor', [1, 2, 3]));
    }

    public function test_bump_version_patch_returns_correct_result(): void
    {
        $this->assertEquals([1, 2, 4], VersioningService::bumpVersion('patch', [1, 2, 3]));
    }

    public function test_increment_major_resets_minor_and_patch(): void
    {
        $result = VersioningService::incrementMajor([5, 8, 12]);
        $this->assertEquals(6, $result[0]);
        $this->assertEquals(0, $result[1]);
        $this->assertEquals(0, $result[2]);
    }

    public function test_increment_minor_resets_patch(): void
    {
        $result = VersioningService::incrementMinor([5, 8, 12]);
        $this->assertEquals(5, $result[0]);
        $this->assertEquals(9, $result[1]);
        $this->assertEquals(0, $result[2]);
    }

    public function test_increment_patch_only_increments_patch(): void
    {
        $result = VersioningService::incrementPatch([5, 8, 12]);
        $this->assertEquals(5, $result[0]);
        $this->assertEquals(8, $result[1]);
        $this->assertEquals(13, $result[2]);
    }

    public function test_default_case_returns_unchanged_version(): void
    {
        $this->assertEquals([1, 2, 3], VersioningService::bumpVersion('invalid', [1, 2, 3]));
        $this->assertEquals([0, 0, 0], VersioningService::bumpVersion('unknown', [0, 0, 0]));
    }

    public function test_bump_version_from_zero(): void
    {
        $this->assertEquals([1, 0, 0], VersioningService::bumpVersion('major', [0, 0, 0]));
        $this->assertEquals([0, 1, 0], VersioningService::bumpVersion('minor', [0, 0, 0]));
        $this->assertEquals([0, 0, 1], VersioningService::bumpVersion('patch', [0, 0, 0]));
    }
}
