<?php

namespace RaiseStudio\FilamentMediaLibrary\Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase as BaseTestCase;

/**
 * 包内测试基类：仅迁移本包 6 个迁移（不跑 testbed 全量迁移，避免 forge 等迁移在隔离环境报错），
 * 并用 DatabaseTransactions 在 :memory: sqlite 上隔离每个测试的数据。
 *
 * 注意：testbed 连接带有 rs_ 表前缀，迁移仓库表为 rs_migrations；手动 migrator 需先建仓库。
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $migrator = $this->app->make('migrator');
        $repository = $migrator->getRepository();

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }

        $migrator->run([
            realpath(__DIR__.'/../database/migrations'),
        ]);
    }
}
