# Blade for WordPress

Use the very latest of Laravel's Blade templating engine in your WordPress theme. Install it via composer and start using Blade in your theme now.

The class is inspired by the Blade for [WordPress plugin by Mikael Mattsson](https://github.com/MikaelMattsson/blade), and relies heavily on the standalone [Laravel Blade by Philo Hermans](https://github.com/PhiloNL).

## What is Blade?

Blade is the simple, yet powerful templating engine provided with Laravel. Unlike other popular PHP templating engines, Blade does not restrict you from using plain PHP code in your views. All Blade views are compiled into plain PHP code and cached until they are modified, meaning Blade adds essentially zero overhead to your theme.

For more about using Blade, [please refer to the documentation](http://laravel.com/docs/5.1/blade).

## Note

By default Blade view files use the `.blade.php` file extension. This does not not fly with the WordPress Template Hierarchy, which searches for certain files (e.g. front-page.php) in the template folder.

Instead only partials (searched for in the `templates/`-folder) use the `.blade.php` syntax. Regular template files use the `.php` extension. They are since cached within the Blade views(templates) folder. This way WordPress recognizes the template, accepts it, renders it and halts the regular WordPress include process.

## Install

Install it using Composer

`composer require tormjens/wp-blade`

## Usage

First you must introduce the class to your WordPress enviroment, this can be done in the `functions.php` of your theme.

```php
new \Tormorten\WPBlade\Blade(
	get_template_directory() . '/templates',
	WP_CONTENT_DIR . '/blade_cache'
);
```

In this instance we're telling Blade to look for partials in the `templates/` folder in our theme folder. We're also setting the cache-folder to a folder called `blade_cache` within our wp-content folder. This needs to be writeable by PHP, as... well, we're writing our cached files to it.

Now Blade is availiable in your theme. If you wanted to "Bladify" the single.php template, you would create `single.php` and put it in the root of your theme, and just go ahead and blade the crap out of it.

```php
@extends('layout')

@section('content')

	<p>This is my awesome single.php</p>

@endsection
```

It's that easy.

### Custom Tags

In addition I've also added all the neat template tags which was present in the Blade for WordPress plugin, plus some new ones.

#### Custom WP_Query

*** Blade ***
```php
	@wpquery(array('post_type' => 'post'))
		<h1>{{ the_title() }}</h1>
	@wpempty
		<h1>No posts found</h1>
	@wpend
```

*** Produces ***
```php
	<?php $bladequery = new WP_Query( array( 'post_type' => 'post' ) ); ?>
	<?php if ( $bladequery->have_posts() ) : ?>
	        <?php while ( $bladequery->have_posts() ) : $bladequery->the_post(); ?>
	        	<h1><?php the_title() ?></h1>
	        <?php endwhile; ?>
	<?php else : ?>
	        <h1>No posts found</h1>
	<?php endif; wp_reset_postdata(); ?>
```

#### WordPress Posts Loop

*** Blade ***
```php
	@wpposts
		<h1>{{ the_title() }}</h1>
	@wpempty
		<h1>No posts found</h1>
	@wpend
```

*** Produces ***
```php
	<?php if ( have_posts() ) : ?>
	        <?php while ( have_posts() ) : the_post(); ?>
	        	<h1><?php the_title() ?></h1>
	        <?php endwhile; ?>
	<?php else : ?>
	        <h1>No posts found</h1>
	<?php endif; wp_reset_postdata(); ?>
```

#### Advanced Custom Fields: Repeater/Flexible Content
*** Blade ***
```php
	@acf('images')
		<img src="@subfield('image')" />
	@acfempty
		<h1>No images found</h1>
	@wpend
```

*** Produces ***
```php
	<?php if ( have_rows('images') ) : ?>
	        <?php while ( have_rows('images') ) : the_row(); ?>
	        	<img src="<?php the_sub_field('image') ?>" />
	        <?php endwhile; ?>
	<?php else : ?>
	        <h1>No images found</h1>
	<?php endif; ?>
```

#### Advanced Custom Fields: Display Field
*** Blade ***
```php
	@field('name')
```

*** Produces ***
```php
	<?php if ( get_field('name') ) : ?>
		<?php the_field('name'); ?>
	<?php endif; ?>
```

#### Advanced Custom Fields: Has Field
*** Blade ***
```php
	@hasfield('name')
		<div class="my-name-field">@field('name')</div>
	@else
		<div class="no-name-field">This person is nameless</div>
	@endif
```

*** Produces ***
```php
	<?php if ( get_field('name') ) : ?>
		<div class="my-name-field"><?php the_field('name'); ?></div>
	<?php else : ?>
		<div class="no-name-field">This person is nameless</div>
	<?php endif; ?>
```



