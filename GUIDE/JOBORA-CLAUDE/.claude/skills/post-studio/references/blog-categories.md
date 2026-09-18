# Blog categories (canonical)

Use this list for **step 0** category selection. Never invent categories.
When the user picks one, use `blog_category_name` as the label and
`blog_category_description` as the **research niche** for Tavily and ideation.

| # | `blog_category_name` | `blog_category_description` |
|---|----------------------|-----------------------------|
| 1 | AI | Artificial Intelligence trends, tools and insights |
| 2 | Software | Software development, engineering and best practices |
| 3 | Marketing Online | Digital marketing strategies, SEO and content marketing |
| 4 | Social Network | Social media platforms, networking and community building |

Source shape (matches blog seeders / `BlogCategoryEloquentModel`):

```php
$blogCategories = [
    [
        'blog_category_name' => 'AI',
        'blog_category_description' => 'Artificial Intelligence trends, tools and insights',
        'blog_category_image' => null,
        'user_id' => 1,
    ],
    [
        'blog_category_name' => 'Software',
        'blog_category_description' => 'Software development, engineering and best practices',
        'blog_category_image' => null,
        'user_id' => 1,
    ],
    [
        'blog_category_name' => 'Marketing Online',
        'blog_category_description' => 'Digital marketing strategies, SEO and content marketing',
        'blog_category_image' => null,
        'user_id' => 1,
    ],
    [
        'blog_category_name' => 'Social Network',
        'blog_category_description' => 'Social media platforms, networking and community building',
        'blog_category_image' => null,
        'user_id' => 1,
    ],
];
```

When persisting a draft to the Laravel app, map `blog_category_name` to the
category UUID from the CMS (`PostController` category list) — do not guess UUIDs;
use the API/UI list or ask the user if ambiguous.
