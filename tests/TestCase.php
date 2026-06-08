<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Verificación de seguridad: NUNCA usar MySQL en tests
        if (config('database.default') === 'mysql') {
            $this->fail('PELIGRO: Los tests están configurados para usar MySQL. Abortando para proteger los datos.');
        }
        
        // Asegurar que estamos usando SQLite en memoria
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            $this->fail('Los tests deben usar SQLite en memoria (:memory:)');
        }
    }
}
