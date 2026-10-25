<?php

namespace Finest\Platform\Tests;

use Finest\Platform\Ids\HasPrefixedId;
use Finest\Platform\Tenancy\BelongsToOrganization;
use Finest\Platform\Tenancy\CurrentOrganization;
use Finest\Platform\Tenancy\NoCurrentOrganization;
use Finest\Platform\Tenancy\Owned;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class TenancyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('things', function (Blueprint $table) {
            $table->string('id', 30)->primary();
            $table->string('organization_id');
            $table->string('name');
        });
    }

    public function test_an_organization_sees_and_makes_only_its_own_records(): void
    {
        $current = app(CurrentOrganization::class);
        $current->set('org-a');
        $ours = Thing::create(['name' => 'ours']);
        $current->set('org-b');
        Thing::create(['name' => 'theirs']);

        $this->assertSame(['theirs'], Thing::pluck('name')->all());
        $this->assertNull(Thing::find($ours->id));
        $this->assertStringStartsWith('thg_', $ours->id);
        $this->assertSame('org-a', $ours->organization_id);
        $this->assertTrue(Validator::make(['id' => $ours->id], ['id' => [new Owned(Thing::class)]])->fails());
    }

    public function test_without_a_current_organization_nothing_is_read(): void
    {
        $this->expectException(NoCurrentOrganization::class);

        Thing::query()->get();
    }
}

class Thing extends Model
{
    use BelongsToOrganization, HasPrefixedId;

    public const ID_PREFIX = 'thg';

    public $timestamps = false;

    protected $fillable = ['name'];
}
