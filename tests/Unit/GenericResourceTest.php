<?php

namespace LinkedData\SPARQL\Tests\Unit;

use LinkedData\SPARQL\Eloquent\GenericResource;
use LinkedData\SPARQL\Tests\TestCase;

class GenericResourceTest extends TestCase
{
    public function test_generic_resource_extends_model(): void
    {
        $resource = new GenericResource;

        $this->assertInstanceOf(\LinkedData\SPARQL\Eloquent\Model::class, $resource);
    }

    public function test_generic_resource_has_no_guarded_attributes(): void
    {
        $resource = new GenericResource;

        $this->assertEquals([], $resource->getGuarded());
    }

    public function test_generic_resource_has_default_table_name(): void
    {
        $resource = new GenericResource;

        // Laravel generates a default table name from the class name
        $this->assertEquals('generic_resources', $resource->getTable());
    }

    public function test_make_creates_instance_with_uri(): void
    {
        $uri = 'http://example.com/resource/1';
        $resource = GenericResource::make($uri);

        $this->assertInstanceOf(GenericResource::class, $resource);
        $this->assertEquals($uri, $resource->getKey());
    }

    public function test_make_creates_instance_with_uri_and_rdf_class(): void
    {
        $uri = 'http://example.com/resource/1';
        $rdfClass = 'http://schema.org/Product';

        $resource = GenericResource::make($uri, $rdfClass);

        $this->assertInstanceOf(GenericResource::class, $resource);
        $this->assertEquals($uri, $resource->getKey());
        $this->assertEquals($rdfClass, $resource->getTable());
    }

    public function test_make_without_rdf_class_uses_default_table(): void
    {
        $uri = 'http://example.com/resource/1';
        $resource = GenericResource::make($uri);

        // When no RDF class is provided, it uses the default table name
        $this->assertEquals('generic_resources', $resource->getTable());
    }

    public function test_generic_resource_can_set_attributes(): void
    {
        $resource = GenericResource::make('http://example.com/product/1', 'http://schema.org/Product');

        $resource->setAttribute('http://schema.org/name', 'Widget');
        $resource->setAttribute('http://schema.org/price', 19.99);

        $this->assertEquals('Widget', $resource->getAttribute('http://schema.org/name'));
        $this->assertEquals(19.99, $resource->getAttribute('http://schema.org/price'));
    }

    public function test_generic_resource_allows_mass_assignment(): void
    {
        $resource = new GenericResource([
            'name' => 'Product Name',
            'price' => 99.99,
        ]);

        $this->assertEquals('Product Name', $resource->getAttribute('name'));
        $this->assertEquals(99.99, $resource->getAttribute('price'));
    }

    public function test_generic_resource_can_use_different_rdf_classes(): void
    {
        $person = GenericResource::make('http://example.com/person/1', 'http://xmlns.com/foaf/0.1/Person');
        $organization = GenericResource::make('http://example.com/org/1', 'http://xmlns.com/foaf/0.1/Organization');

        $this->assertEquals('http://xmlns.com/foaf/0.1/Person', $person->getTable());
        $this->assertEquals('http://xmlns.com/foaf/0.1/Organization', $organization->getTable());
    }

    public function test_make_returns_static_instance(): void
    {
        $resource = GenericResource::make('http://example.com/resource/1');

        $this->assertInstanceOf(GenericResource::class, $resource);
    }

    public function test_generic_resource_with_short_uri(): void
    {
        $resource = GenericResource::make('ex:resource1', 'schema:Product');

        $this->assertEquals('ex:resource1', $resource->getKey());
        $this->assertEquals('schema:Product', $resource->getTable());
    }
}
