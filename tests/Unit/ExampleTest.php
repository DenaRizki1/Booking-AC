<?php

it('memakai timezone Asia/Jakarta', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');
});
