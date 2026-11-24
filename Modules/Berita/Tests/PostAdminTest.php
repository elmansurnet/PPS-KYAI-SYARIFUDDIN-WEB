<?php

use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Modules\Berita\Database\Seeders\BeritaSeeder;
use Modules\Berita\Filament\Resources\CategoryResource;
use Modules\Berita\Filament\Resources\PostResource;
use Modules\Berita\Filament\Resources\PostResource\Pages\CreatePost;
use Modules\Berita\Filament\Resources\PostResource\Pages\EditPost;
use Modules\Berita\Filament\Resources\PostResource\Pages\ListPosts;
use Modules\Berita\Models\Category;
use Modules\Berita\Models\Post;
use Modules\Core\Database\Seeders\RoleSeeder;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(BeritaSeeder::class);
    Filament::setCurrentPanel('admin');
});

function beritaUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

test('test_penulis_berita_can_create_post_but_not_open_settings', function () {
    $writer = beritaUser('penulis_berita');
    $this->actingAs($writer);

    expect(PostResource::canViewAny())->toBeTrue()
        ->and(PostResource::canCreate())->toBeTrue();
    $this->get('/admin/posts')->assertOk();
    $this->get('/admin/posts/create')->assertOk();

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Berita Pertama', 'body' => '<p>Isi</p>', 'status' => 'draft'])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::firstOrFail();
    expect($post->author_id)->toBe($writer->id)->and($post->slug)->toBe('berita-pertama');

    $this->get('/admin/manage-site-settings')->assertForbidden();
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/landing-sections')->assertForbidden();
});

test('author cannot forge author_id and publishing without a date sets published_at to now', function () {
    $writer = beritaUser('penulis_berita');
    $other = beritaUser('admin');
    $this->actingAs($writer);

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Terbit Langsung', 'body' => '<p>Isi</p>', 'status' => 'published', 'author_id' => $other->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::firstOrFail();
    expect($post->author_id)->toBe($writer->id)
        ->and($post->published_at)->not->toBeNull()
        ->and($post->published_at->diffInSeconds(now(), true))->toBeLessThan(5);
});

test('rich text is sanitised when saved through the form', function () {
    $this->actingAs(beritaUser('penulis_berita'));

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Xss', 'body' => '<p>Halo</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>', 'status' => 'draft'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Post::firstOrFail()->getRawOriginal('body'))->not->toContain('script')->not->toContain('javascript');
});

test('author can edit and delete only own posts, admin can manage all', function () {
    $writer = beritaUser('penulis_berita');
    $mine = Post::factory()->create(['author_id' => $writer->id]);
    $theirs = Post::factory()->create();

    $this->actingAs($writer);
    expect($writer->can('update', $mine))->toBeTrue()
        ->and($writer->can('delete', $mine))->toBeTrue()
        ->and($writer->can('view', $theirs))->toBeTrue()
        ->and($writer->can('update', $theirs))->toBeFalse()
        ->and($writer->can('delete', $theirs))->toBeFalse()
        ->and($writer->can('deleteAny', Post::class))->toBeFalse();
    $this->get('/admin/posts/'.$mine->id.'/edit')->assertOk();
    $this->get('/admin/posts/'.$theirs->id.'/edit')->assertForbidden();

    $admin = beritaUser('admin');
    expect($admin->can('update', $theirs))->toBeTrue()
        ->and($admin->can('delete', $theirs))->toBeTrue()
        ->and($admin->can('deleteAny', Post::class))->toBeTrue();

    $super = User::factory()->create();
    $super->assignRole('superadmin');
    expect($super->can('update', $theirs))->toBeTrue();
});

test('list page shows all posts to the author but sorted by published_at desc', function () {
    $writer = beritaUser('penulis_berita');
    $old = Post::factory()->create(['published_at' => now()->subDays(3)]);
    $new = Post::factory()->create(['published_at' => now()->subDay()]);

    $this->actingAs($writer);
    Livewire::test(ListPosts::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

test('role access to berita resources', function () {
    $this->actingAs(beritaUser('panitia_pendaftaran'));
    expect(PostResource::canViewAny())->toBeFalse()->and(CategoryResource::canViewAny())->toBeFalse();
    $this->get('/admin/posts')->assertForbidden();
    $this->get('/admin/categories')->assertForbidden();

    $this->actingAs(beritaUser('admin'));
    expect(PostResource::canViewAny())->toBeTrue()->and(CategoryResource::canViewAny())->toBeTrue();
    $this->get('/admin/posts')->assertOk();
    $this->get('/admin/categories')->assertOk();

    $this->actingAs(beritaUser('penulis_berita'));
    expect(CategoryResource::canViewAny())->toBeTrue()->and(CategoryResource::canCreate())->toBeTrue();
    $category = Category::factory()->create();
    expect(CategoryResource::canEdit($category))->toBeFalse()->and(CategoryResource::canDelete($category))->toBeFalse();
});

test('seeder is idempotent and gives admin everything and author only the allowed permissions', function () {
    $this->seed(BeritaSeeder::class);
    $this->seed(BeritaSeeder::class);

    $admin = beritaUser('admin');
    foreach (['ViewAny:Post', 'View:Post', 'Create:Post', 'Update:Post', 'Delete:Post', 'DeleteAny:Post', 'ManageAll:Post', 'ViewAny:Category', 'Create:Category', 'Update:Category', 'Delete:Category'] as $permission) {
        expect($admin->can($permission))->toBeTrue($permission);
    }

    $writer = beritaUser('penulis_berita');
    foreach (['ViewAny:Post', 'View:Post', 'Create:Post', 'Update:Post', 'Delete:Post', 'ViewAny:Category', 'Create:Category'] as $permission) {
        expect($writer->can($permission))->toBeTrue($permission);
    }
    foreach (['ManageAll:Post', 'DeleteAny:Post', 'Update:Category', 'Delete:Category', 'Manage:SiteSettings', 'ViewAny:LandingSection'] as $permission) {
        expect($writer->can($permission))->toBeFalse($permission);
    }

    $panitia = beritaUser('panitia_pendaftaran');
    expect($panitia->can('ViewAny:Post'))->toBeFalse();
});

test('db:seed on a fresh database gives the berita permissions', function () {
    $this->artisan('db:seed')->assertSuccessful();

    expect(beritaUser('admin')->can('ManageAll:Post'))->toBeTrue()
        ->and(beritaUser('penulis_berita')->can('Create:Post'))->toBeTrue();
});

test('cover upload accepts jpg and rejects svg and oversize files', function () {
    Storage::fake('public');
    $this->actingAs(beritaUser('penulis_berita'));

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Bersampul', 'body' => '<p>x</p>', 'status' => 'draft', 'cover_path' => UploadedFile::fake()->image('a.jpg', 800, 500)])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::firstOrFail();
    expect($post->cover_path)->toStartWith('berita/')->and($post->cover_path)->not->toContain('a.jpg');
    Storage::disk('public')->assertExists($post->cover_path);

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Svg', 'body' => '<p>x</p>', 'status' => 'draft', 'cover_path' => UploadedFile::fake()->create('a.svg', 5, 'image/svg+xml')])
        ->call('create')
        ->assertHasFormErrors(['cover_path']);

    Livewire::test(CreatePost::class)
        ->fillForm(['title' => 'Besar', 'body' => '<p>x</p>', 'status' => 'draft', 'cover_path' => UploadedFile::fake()->image('b.jpg')->size(5000)])
        ->call('create')
        ->assertHasFormErrors(['cover_path']);
});

test('editing keeps the slug stable and author as is', function () {
    $writer = beritaUser('penulis_berita');
    $post = Post::factory()->create(['author_id' => $writer->id, 'title' => 'Judul Awal', 'slug' => null]);
    $this->actingAs($writer);

    Livewire::test(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Judul Diubah'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->fresh()->slug)->toBe('judul-awal')->and($post->fresh()->title)->toBe('Judul Diubah');
});

test('category slug must be unique and generated from the name', function () {
    $this->actingAs(beritaUser('admin'));
    Category::factory()->create(['name' => 'Kegiatan', 'slug' => 'kegiatan']);

    $second = Category::factory()->create(['name' => 'Kegiatan', 'slug' => null]);
    expect($second->slug)->toBe('kegiatan-2');
});

test('author can create a category inline from the post form', function () {
    $this->actingAs(beritaUser('penulis_berita'));

    Livewire::test(CreatePost::class)
        ->callAction(TestAction::make('createOption')->schemaComponent('category_id', schema: 'form'), ['name' => 'Kegiatan Baru'])
        ->assertHasNoFormErrors();

    expect(Category::where('name', 'Kegiatan Baru')->exists())->toBeTrue();
});

test('panitia cannot create posts or categories', function () {
    $this->actingAs(beritaUser('panitia_pendaftaran'));

    expect(PostResource::canCreate())->toBeFalse()->and(CategoryResource::canCreate())->toBeFalse();
    $this->get('/admin/posts/create')->assertForbidden();
});

test('author has no bulk delete and cannot remove others posts, admin can bulk delete', function () {
    $writer = beritaUser('penulis_berita');
    $mine = Post::factory()->count(2)->create(['author_id' => $writer->id]);
    $theirs = Post::factory()->count(2)->create();
    $ids = $mine->merge($theirs)->pluck('id')->all();
    $bulk = TestAction::make(DeleteBulkAction::class)->table()->bulk();

    // Penulis: aksi hapus massal tersembunyi (deleteAny ditolak) dan tidak bisa dipaksa dipanggil.
    $this->actingAs($writer);
    $component = Livewire::test(ListPosts::class)->selectTableRecords($ids)->assertActionHidden($bulk);
    try {
        $component->callAction($bulk);
    } catch (Throwable) {
        // diharapkan: aksi tidak dapat dijalankan
    }
    expect(Post::count())->toBe(4);

    // Penulis tetap bisa menghapus miliknya sendiri satu per satu, bukan milik orang lain.
    expect($writer->can('delete', $mine->first()))->toBeTrue()->and($writer->can('delete', $theirs->first()))->toBeFalse();

    // Admin: hapus massal berjalan.
    $this->actingAs(beritaUser('admin'));
    Livewire::test(ListPosts::class)->selectTableRecords($ids)->callAction($bulk);
    expect(Post::count())->toBe(0);
});
