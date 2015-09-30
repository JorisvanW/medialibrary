<?php

namespace CipeMotion\Medialibrary\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class Category extends Model
{
    protected $table = 'medialibrary_categories';

    protected $fillable = [
        'name'
    ];

    protected $visible = [
        'name'
    ];

    // Scopes
    public function scopeParents(Builder $builder)
    {
        $builder->whereNull('parent_id');
    }
    public function scopeStructured(Builder $builder)
    {
        $builder->with(['childs'])->whereNull('parent_id');
    }

    // Getters
    public function __toString()
    {
        return $this->name;
    }

    // Setters
    // ...

    // Relations
    public function parent()
    {
        return $this->belongsTo(Category::class);
    }
    public function childs()
    {
        return $this->hasMany(Category::class, 'parent_id')
                    ->orderBy('order', 'ASC');
    }

    // Helpers
    public static function updateArrangement(Collection $collection, array $arrangement)
    {
        $order = 0;

        foreach ($arrangement as $parent) {
            /** @var \CipeMotion\Medialibrary\Entities\Category $category */
            $category = $collection->find(array_get($parent, 'id', 0));
            $children = array_get($parent, 'children', []);

            if (!is_null($category)) {
                $category->order     = $order;
                $category->parent_id = null;
                $category->save();

                if (count($children) > 0) {
                    $childOrder = 0;

                    foreach ($children as $child) {
                        /** @var \CipeMotion\Medialibrary\Entities\Category $childCategory */
                        $childCategory = $collection->find(array_get($child, 'id', 0));

                        if (!is_null($childCategory)) {
                            $childCategory->order     = $childOrder;
                            $childCategory->parent_id = $category->id;
                            $childCategory->save();

                            $childOrder ++;
                        }
                    }
                }

                $order ++;
            }
        }
    }
}
