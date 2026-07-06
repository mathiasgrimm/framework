<?php

namespace Illuminate\Tests\Integration\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\RequiresDatabase;

class SchemaForeignKeyIndexTest extends DatabaseTestCase
{
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $connection = $app['config']->get('database.default');

        $app['config']->set("database.connections.$connection.index_foreign_keys", true);

        $app['config']->set('database.connections.without_fk_indexes', array_merge(
            $app['config']->get("database.connections.$connection"),
            ['index_foreign_keys' => false]
        ));
    }

    public function testForeignKeyColumnIsIndexedAutomatically()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        });

        $this->assertTrue(Schema::hasIndex('posts', ['user_id']));
        $this->assertCount(1, Schema::getForeignKeys('posts'));
    }

    public function testCompositeUniqueCoversFirstForeignKeyOnly()
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained();
            $table->foreignId('category_id')->constrained();
            $table->string('title');
            $table->unique(['author_id', 'title']);
        });

        $this->assertFalse(Schema::hasIndex('books', 'books_author_id_index'));
        $this->assertTrue(Schema::hasIndex('books', ['category_id']));
        $this->assertTrue(Schema::hasIndex('books', ['author_id', 'title'], 'unique'));
    }

    #[RequiresDatabase(['pgsql', 'sqlite', 'sqlsrv'])]
    public function testWithoutIndexSkipsAutomaticIndex()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->withoutIndex();
        });

        $this->assertFalse(Schema::hasIndex('posts', ['user_id']));
        $this->assertCount(1, Schema::getForeignKeys('posts'));
    }

    public function testExplicitIndexIsNotDuplicated()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index()->constrained();
        });

        $this->assertCount(1, collect(Schema::getIndexes('posts'))->filter(
            fn ($index) => $index['columns'] === ['user_id']
        ));
    }

    #[RequiresDatabase(['pgsql', 'sqlite', 'sqlsrv'])]
    public function testConfigDisabledCreatesNoIndex()
    {
        $schema = Schema::connection('without_fk_indexes');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
        });

        $schema->create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
        });

        $this->assertFalse($schema->hasIndex('posts', ['user_id']));
    }

    public function testMultiColumnForeignKeyIsIndexed()
    {
        Schema::create('parent', function (Blueprint $table) {
            $table->id();
            $table->integer('a');
            $table->integer('b');
            $table->unique(['b', 'a']);
        });

        Schema::create('children', function (Blueprint $table) {
            $table->integer('c');
            $table->integer('d');
            $table->foreign(['d', 'c'])->references(['b', 'a'])->on('parent');
        });

        $this->assertTrue(Schema::hasIndex('children', ['d', 'c']));
    }

    public function testCompositeIndexWithForeignKeyAsLeftmostPrefixSkipsImplicitIndex()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->index(['user_id', 'title']);
        });

        $this->assertFalse(Schema::hasIndex('posts', ['user_id']));
        $this->assertTrue(Schema::hasIndex('posts', ['user_id', 'title']));
    }

    public function testCompositeIndexDeclaredAfterConstrainedStillCoversForeignKey()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->unique(['user_id', 'title']);
        });

        $this->assertFalse(Schema::hasIndex('posts', ['user_id']));
        $this->assertTrue(Schema::hasIndex('posts', ['user_id', 'title'], 'unique'));
    }

    public function testNonLeftmostCompositeIndexDoesNotCoverForeignKey()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('title');
            $table->unique(['title', 'user_id']);
        });

        $this->assertTrue(Schema::hasIndex('posts', ['user_id']));
    }
}
