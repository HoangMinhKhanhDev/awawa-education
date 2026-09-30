<?php

namespace Tests;

use App\Models\NotebookSetting;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Static memo trong request (NotebookSetting, Navigation) phải reset
        // giữa các test, nếu không dữ liệu test trước rò sang test sau.
        NotebookSetting::flushMemo();
        Navigation::flushMemo();
    }
}
