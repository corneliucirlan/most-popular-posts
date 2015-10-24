# Most Popular Posts

This class was designed to help [Wordpress](https://wordpress.org "Wordpress.org") site owners keep track of how many times their posts have been read. Thus, it will only add a custom column into the Posts admin page with the view count.

## How it works

Every time a post is loaded, an [AJAX](https://en.wikipedia.org/wiki/Ajax_(programming)) script fires to either create the custom key and set the value to 1 or increment the existing key value.

## How to use

Just add a small snippet of code to your `functions.php` file
```php
include_once('path/to/most-popular-posts.php');
```
