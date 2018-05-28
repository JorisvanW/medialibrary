## CipeMotion Media Library

A media library package making it easy to implement your own WordPress style media picker component.

This package depends on [`intervention/image`](https://github.com/Intervention/image).

### Configuration

Add the service providers to your providers array in `app.php`.

```
CipeMotion\Medialibrary\ServiceProvider::class,
Intervention\Image\ImageServiceProvider::class
```

Optional: Add the [`intervention/image`](https://github.com/Intervention/image) Facade to the aliases array in `app.php`.

```
'Image' => Intervention\Image\Facades\Image::class,
```

Publish the config file:

```
php artisan vendor:publish --provider="CipeMotion\Medialibrary\ServiceProvider" --tag="config"
```

Read through the config file and change what is needed.

### Database

Publish the migrations file:

```
php artisan vendor:publish --provider="CipeMotion\Medialibrary\ServiceProvider" --tag="migrations"
```

Run the migrations:

```
php artisan migrate
```

### Relations

On your owner model add:

```
public function files()
{
    return $this->hasMany(\CipeMotion\Medialibrary\Entities\File::class, 'owner_id');
}
```

On your models add:

```
public function attachebles()
{
    return $this->morphToMany(\CipeMotion\Medialibrary\Entities\File::class, 'attachable', 'medialibrary_attachable');
}
```


### Services (external file pickers)

#### DropBox

#### OneDrive

#### Google Drive
