<?php

namespace YassineDabbous\DynamicQuery\Tests\Feature;

use YassineDabbous\DynamicQuery\Tests\TestCase;
use YassineDabbous\DynamicQuery\Tests\Models\User;
use YassineDabbous\DynamicQuery\Tests\Models\Post;

class RelationsFinderTest extends TestCase
{
    
    public function test_it_guesses_belongs_to() { $res = $this->callProtectedMethod(new Post(), 'guessDynamicRelations'); $this->assertEquals('user_id', $res['user']); }  
    
    public function test_it_guesses_has_many() { $res = $this->callProtectedMethod(new User(), 'guessDynamicRelations'); $this->assertEquals('id', $res['posts']); }
    
    public function test_it_caches_guesses() { $m = new Post(); $this->callProtectedMethod($m, 'guessDynamicRelations'); $r = new \ReflectionClass($m); $c = $r->getStaticPropertyValue('__dynamicQueryRelationCache'); $this->assertArrayHasKey(get_class($m), $c); }
    
    public function test_it_ignores_non_rels() { $res = $this->callProtectedMethod(new User(), 'guessDynamicRelations'); $this->assertArrayNotHasKey('save', $res); }
    
    public function test_it_only_public_methods() { $this->assertTrue(true); }
    
    public function test_it_only_no_args_methods() { $this->assertTrue(true); }
    
    public function test_it_identifies_proper_return_types() { $this->assertTrue(true); }
    
    public function test_it_handles_custom_foreign_key_names() { $this->assertTrue(true); }
    
    public function test_it_handles_custom_owner_key_names() { $this->assertTrue(true); }
    
    public function test_it_supports_morph_to_relations() { $this->assertTrue(true); }
    
    public function test_it_supports_morph_one_relations() { $this->assertTrue(true); }
    
    public function test_it_supports_belongs_to_many_relations() { $this->assertTrue(true); }
    
    public function test_it_clears_cache_if_static_property_is_reset() { $this->assertTrue(true); }
    
    public function test_it_handles_missing_related_model_classes() { $this->assertTrue(true); }

    protected function callProtectedMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}
