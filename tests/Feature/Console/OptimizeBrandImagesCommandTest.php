<?php

use function Pest\Laravel\artisan;

/**
 * The command rewrites a real file in `public/img/`, so every test here restores
 * the artefact it found. Nothing in the suite may leave the served asset in a
 * different state than it started.
 */
beforeEach(function () {
    $this->target = base_path('public/img/hero-glow.webp');
    $this->master = base_path('BRAND/hero-glow.webp');

    $this->backup = is_file($this->target) ? file_get_contents($this->target) : null;
    $this->backupMtime = is_file($this->target) ? filemtime($this->target) : null;
});

afterEach(function () {
    if ($this->backup === null) {
        @unlink($this->target);

        return;
    }

    file_put_contents($this->target, $this->backup);
    touch($this->target, $this->backupMtime);
});

test('it re-encodes the master into a dramatically smaller artefact', function () {
    artisan('brand:optimize-images', ['--force' => true])->assertSuccessful();

    clearstatcache();

    expect(filesize($this->target))
        ->toBeLessThan(filesize($this->master) / 4);
});

test('it never modifies the design master', function () {
    $before = md5_file($this->master);

    artisan('brand:optimize-images', ['--force' => true])->assertSuccessful();

    expect(md5_file($this->master))->toBe($before);
});

test('the artefact is still a valid webp image at the master resolution', function () {
    artisan('brand:optimize-images', ['--force' => true])->assertSuccessful();

    clearstatcache();

    $master = getimagesize($this->master);
    $artefact = getimagesize($this->target);

    expect($artefact)->not->toBeFalse()
        ->and($artefact['mime'])->toBe('image/webp')
        // The master is narrower than the 1440px ceiling, so `scaleDown` must
        // leave it alone rather than upscaling it.
        ->and($artefact[0])->toBe($master[0])
        ->and($artefact[1])->toBe($master[1]);
});

test('it skips an artefact that is already newer than its master', function () {
    artisan('brand:optimize-images', ['--force' => true])->assertSuccessful();

    clearstatcache();
    $encodedAt = filemtime($this->target);

    // Without --force the freshness check should short-circuit the encode.
    artisan('brand:optimize-images')
        ->expectsOutputToContain('up to date')
        ->assertSuccessful();

    clearstatcache();

    expect(filemtime($this->target))->toBe($encodedAt);
});

test('a stale artefact is re-encoded without --force', function () {
    // Backdate the artefact so it looks older than the master.
    touch($this->target, filemtime($this->master) - 60);
    clearstatcache();

    artisan('brand:optimize-images')->assertSuccessful();

    clearstatcache();

    expect(filemtime($this->target))->toBeGreaterThanOrEqual(filemtime($this->master));
});

test('the quality option is honoured', function () {
    artisan('brand:optimize-images', ['--force' => true, '--quality' => 90])
        ->assertSuccessful();

    clearstatcache();
    $high = filesize($this->target);

    artisan('brand:optimize-images', ['--force' => true, '--quality' => 20])
        ->assertSuccessful();

    clearstatcache();
    $low = filesize($this->target);

    expect($low)->toBeLessThan($high);
});
