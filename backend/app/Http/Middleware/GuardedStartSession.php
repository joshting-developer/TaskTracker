<?php

namespace App\Http\Middleware;

use App\Factories\SessionLockFactory;
use App\Http\Session\SessionLease;
use Closure;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;

/** 保留框架Session／CSRF生命週期，MySQL以無租期連線鎖避免慢請求覆寫新Session。 */
class GuardedStartSession extends StartSession
{
    /**
     * 注入框架Session／Cache與鎖組裝邊界，不在長駐singleton保留租戶。
     *
     * @param  SessionManager  $manager  Laravel Session管理
     * @param  Factory  $cache  Session blocking用的cache
     * @param  SessionLockFactory  $locks  依Session資料庫建立命名鎖
     */
    public function __construct(SessionManager $manager, Factory $cache, private SessionLockFactory $locks)
    {
        parent::__construct($manager, fn () => $cache);
    }

    /**
     * 從讀Session前鎖至Session保存完成，失敗亦釋放；不靠延長固定TTL掩蓋競態。
     *
     * @param  Request  $request  當次HTTP
     * @param  Session  $session  框架尚未載入的Session
     * @param  Closure  $next  後續中介層與業務
     * @return mixed 原框架回應
     */
    protected function handleRequestWhileBlocking(Request $request, $session, Closure $next)
    {
        if (! $this->locks->supported()) {
            return parent::handleRequestWhileBlocking($request, $session, $next);
        }
        $lease = $this->locks->acquire($session->getName(), $session->getId(), $request->route()->waitsFor() ?? 10);
        $request->attributes->set(SessionLease::class, $lease);
        try {
            return $this->handleStatefulRequest($request, $session, $next);
        } finally {
            $request->attributes->remove(SessionLease::class);
            $lease->release();
        }
    }

    /**
     * 保存前再次確認仍持有原鎖；重連不能保存舊快照。
     *
     * @param  Request  $request  當次HTTP
     * @return void
     */
    protected function saveSession($request)
    {
        $request->attributes->get(SessionLease::class)?->assertHeld();
        parent::saveSession($request);
    }
}
