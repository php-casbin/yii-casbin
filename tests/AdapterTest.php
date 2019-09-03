<?php

namespace CasbinAdapter\Yii\Tests;

use PHPUnit\Framework\TestCase;
use yii\web\Application;
use Yii;
use CasbinAdapter\Yii\Models\CasbinRule;

class AdapterTest extends TestCase
{
    protected $app;

    public function testEnforce()
    {
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data1', 'read'));

        $this->assertFalse(Yii::$app->casbin->enforce('bob', 'data1', 'read'));
        $this->assertTrue(Yii::$app->casbin->enforce('bob', 'data2', 'write'));

        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data2', 'write'));
    }

    public function testAddPolicy()
    {
        $this->assertFalse(Yii::$app->casbin->enforce('eve', 'data3', 'read'));
        Yii::$app->casbin->addPermissionForUser('eve', 'data3', 'read');
        $this->assertTrue(Yii::$app->casbin->enforce('eve', 'data3', 'read'));
    }

    public function testSavePolicy()
    {
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data4', 'read'));

        $model = Yii::$app->casbin->getModel();
        $model->clearPolicy();
        $model->addPolicy('p', 'p', ['alice', 'data4', 'read']);

        $adapter = Yii::$app->casbin->getAdapter();
        $adapter->savePolicy($model);
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data4', 'read'));
    }

    public function testRemovePolicy()
    {
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data5', 'read'));

        Yii::$app->casbin->addPermissionForUser('alice', 'data5', 'read');
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data5', 'read'));

        Yii::$app->casbin->deletePermissionForUser('alice', 'data5', 'read');
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data5', 'read'));
    }

    public function testRemoveFilteredPolicy()
    {
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data1', 'read'));
        Yii::$app->casbin->removeFilteredPolicy(1, 'data1');
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data1', 'read'));
        $this->assertTrue(Yii::$app->casbin->enforce('bob', 'data2', 'write'));
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data2', 'write'));
        Yii::$app->casbin->removeFilteredPolicy(1, 'data2', 'read');
        $this->assertTrue(Yii::$app->casbin->enforce('bob', 'data2', 'write'));
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data2', 'read'));
        $this->assertTrue(Yii::$app->casbin->enforce('alice', 'data2', 'write'));
        Yii::$app->casbin->removeFilteredPolicy(2, 'write');
        $this->assertFalse(Yii::$app->casbin->enforce('bob', 'data2', 'write'));
        $this->assertFalse(Yii::$app->casbin->enforce('alice', 'data2', 'write'));
    }

    public function createApplication()
    {
        $config = require __DIR__.'/../vendor/yiisoft/yii2-app-basic/config/web.php';
        $config['components']['casbin'] = require __DIR__.'/../config/casbin.php';

        $config['components']['db']['dsn'] = 'mysql:host='.$this->env('DB_HOST', '127.0.0.1').';port='.$this->env('DB_PORT', '3306').';dbname='.$this->env('DB_DATABASE', 'casbin');
        $config['components']['db']['username'] = $this->env('DB_USERNAME', 'root');
        $config['components']['db']['password'] = $this->env('DB_PASSWORD', '');

        return new Application($config);
    }

    /**
     * init table.
     */
    protected function initTable()
    {
        $db = CasbinRule::getDb();
        $tableName = CasbinRule::tableName();
        $table = $db->getTableSchema($tableName);
        if ($table) {
            $db->createCommand()->dropTable($tableName)->execute();
        }

        Yii::$app->casbin->init();

        Yii::$app->db->createCommand()->batchInsert(
            $tableName,
            ['ptype', 'v0', 'v1', 'v2'],
            [
                ['p', 'alice', 'data1', 'read'],
                ['p', 'bob', 'data2', 'write'],
                ['p', 'data2_admin', 'data2', 'read'],
                ['p', 'data2_admin', 'data2', 'write'],
                ['g', 'alice', 'data2_admin', null],
            ]
        )->execute();
    }

    /**
     * Refresh the application instance.
     */
    protected function refreshApplication()
    {
        $this->app = $this->createApplication();
    }

    /**
     * This method is called before each test.
     */
    protected function setUp()/* The :void return type declaration that should be here would cause a BC issue */
    {
        if (!$this->app) {
            $this->refreshApplication();
        }

        $this->initTable();
    }

    /**
     * This method is called after each test.
     */
    protected function tearDown()/* The :void return type declaration that should be here would cause a BC issue */
    {
    }

    protected function env($key, $default = null)
    {
        $value = getenv($key);
        if (is_null($default)) {
            return $value;
        }

        return false === $value ? $default : $value;
    }
}
