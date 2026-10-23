# Custom post types and taxonomies

Register them in the website theme's `functions.php`. This is the only supported place: CodeCanvas has no content-type screen and no registry stored in the database, so what you register is ordinary reviewable code that Git, Studio and an MCP client all see.

```php
function my_site_content_types() {
    register_post_type( 'project', array(
        'labels'       => array( 'name' => __( 'Projects', 'my-site' ), 'singular_name' => __( 'Project', 'my-site' ) ),
        'public'       => true,
        'has_archive'  => true,
        'show_in_rest' => true,
        'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'rewrite'      => array( 'slug' => 'projects' ),
    ) );
    register_taxonomy( 'project_category', array( 'project' ), array(
        'labels'       => array( 'name' => __( 'Project categories', 'my-site' ) ),
        'public'       => true,
        'hierarchical' => true,
        'show_in_rest' => true,
    ) );
}
add_action( 'init', 'my_site_content_types', 20 );
```

Rules worth following:

- Prefix keys you own and never reuse a key for different content; a key is permanent once records exist.
- Flush permalinks once after adding or renaming a slug (**Settings → Permalinks → Save**), or `flush_rewrite_rules( false )` in an activation hook. Never flush on every request.
- Then add the templates that render the new routes: `archive-project.php`, `single-project.php`, `taxonomy-project_category.php`.
- Keep the registration in the theme rather than the plugin: it survives CodeCanvas updates, and it disappears cleanly if the theme is removed.

Existing records are not deleted if you remove a registration; they stay in the database and become hidden from the admin until a theme registers the type again.
