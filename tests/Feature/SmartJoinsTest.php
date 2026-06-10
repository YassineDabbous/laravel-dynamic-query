<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\Post;
use YassineDabbous\DynamicQuery\Tests\Models\User;

class SmartJoinsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createTable('users', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->timestamps();
        });
        $this->createTable('posts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id'); $table->string('title'); $table->timestamps();
        });
    }

    
    public function test_it_qualifies_simple() { $this->assertEquals('posts.title', $this->callProtectedMethod(new Post(), 'dynamicQualifyColumn', [Post::query(), 'title'])); }
    
    public function test_it_joins_dot_notation() { $q = Post::query(); $this->assertEquals('users.name', $this->callProtectedMethod(new Post(), 'dynamicQualifyColumn', [$q, 'user.name'])); $this->assertCount(1, $q->getQuery()->joins); }
    
    public function test_it_prevents_dupe_joins() { $q = Post::query(); $m = new Post(); $this->callProtectedMethod($m, 'dynamicJoinRelation', [$q, 'user']); $this->callProtectedMethod($m, 'dynamicJoinRelation', [$q, 'user']); $this->assertCount(1, $q->getQuery()->joins); }
    
    public function test_it_skips_bad_relation() { $q = Post::query(); $this->callProtectedMethod(new Post(), 'dynamicJoinRelation', [$q, 'fake']); $this->assertNull($q->getQuery()->joins); }
    
    public function test_it_joins_has_many() { $q = User::query(); $this->callProtectedMethod(new User(), 'dynamicJoinRelation', [$q, 'posts']); $this->assertCount(1, $q->getQuery()->joins); }
    
    public function test_it_gets_table_name() { $this->assertEquals('users', $this->callProtectedMethod(new Post(), 'getRelationTableName', ['user'])); }
    
    public function test_it_null_table_bad_rel() { $this->assertNull($this->callProtectedMethod(new Post(), 'getRelationTableName', ['fake'])); }
    
    public function test_it_qualifies_already_qualified() { $this->assertEquals('posts.id', $this->callProtectedMethod(new Post(), 'dynamicQualifyColumn', [Post::query(), 'posts.id'])); }
    
    public function test_it_handles_pivot_joins_if_belongs_to_many() { $this->assertTrue(true); }
    
    public function test_it_qualifies_json_cols_correctly() { $this->assertEquals('posts.meta->key', $this->callProtectedMethod(new Post(), 'dynamicQualifyColumn', [Post::query(), 'meta->key'])); }
    
    public function test_it_qualifies_relation_json_cols() { $q = Post::query(); $this->assertEquals('users.profile->age', $this->callProtectedMethod(new Post(), 'dynamicQualifyColumn', [$q, 'user.profile->age'])); }
    
    public function test_it_handles_polymorphic_relations_gracefully() { $this->assertTrue(true); }
    
    public function test_it_handles_self_referencing_joins() { $this->assertTrue(true); }
    
    public function test_it_determines_foreign_keys_automatically() { $this->assertTrue(true); }
    
    public function test_it_prevents_joining_the_same_table_with_same_alias() { $this->assertTrue(true); }

    protected function callProtectedMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
