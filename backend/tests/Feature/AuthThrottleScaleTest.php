<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** TT-335：帳號相關限流倍數只在testing生效, 正式與local永遠維持原本的次數。 */
class AuthThrottleScaleTest extends TestCase
{
    /**
     * 在指定環境變數下重新讀設定檔, 讀完還原。
     *
     * @param  string  $appEnv  模擬的APP_ENV
     * @param  string  $scale  模擬的TASKTRACKER_AUTH_THROTTLE_SCALE
     * @return int 設定檔算出的倍數
     */
    private function scaleFor(string $appEnv, string $scale): int
    {
        $keys = ['APP_ENV' => $appEnv, 'TASKTRACKER_AUTH_THROTTLE_SCALE' => $scale];
        $saved = [];
        foreach ($keys as $key => $value) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
        try {
            return (require base_path('config/tasktracker.php'))['auth_throttle_scale'];
        } finally {
            foreach ($saved as $key => [$env, $server, $put]) {
                if ($env === null) {
                    unset($_ENV[$key]);
                } else {
                    $_ENV[$key] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$key]);
                } else {
                    $_SERVER[$key] = $server;
                }
                putenv($put === false ? $key : "{$key}={$put}");
            }
        }
    }

    /**
     * 正式與local就算設了倍數也不放寬；testing才依設定放寬, 無效值回到1。
     *
     * @return void
     */
    public function test_scale_only_applies_in_testing(): void
    {
        $this->assertSame(1, $this->scaleFor('production', '50'));
        $this->assertSame(1, $this->scaleFor('local', '50'));
        $this->assertSame(1, $this->scaleFor('staging', '50'));
        $this->assertSame(50, $this->scaleFor('testing', '50'));
        $this->assertSame(1, $this->scaleFor('testing', '0'));
        $this->assertSame(1, $this->scaleFor('testing', 'abc'));
    }

    /**
     * 倍數為1時, 路由的限流次數跟原本一樣（註冊／登入20、驗證碼10、重寄5）。
     *
     * @return void
     */
    public function test_default_routes_keep_original_limits(): void
    {
        $this->assertSame(1, config('tasktracker.auth_throttle_scale'));
        $limits = ['auth.register' => 'throttle:20,1', 'auth.login' => 'throttle:20,1', 'auth.verify-email' => 'throttle:10,1', 'auth.resend-verification' => 'throttle:5,1'];
        foreach ($limits as $name => $throttle) {
            $this->assertContains($throttle, Route::getRoutes()->getByName($name)->middleware(), $name);
        }
    }
}
