<p align="center>
    <img src="https://bloatless.org/img/logo.svg" width="60px" height="80px">
    <h1>Bloatless Blog</h1>
</p>

Bloatless blog is a minimal and simple blog application written in PHP. It is markdown based so new articles
can be published by simply uploading a new text file to your server.

## Features

* Markdown based articles
* RSS feed
* Dynamic image resizing
* Gallery shortcode
* Customizable

## Documentation

### Requirements

* PHP >= 7.2 with the following extensions enabled
  * [XML](https://secure.php.net/manual/en/book.xml.php)
  * [DOM](https://secure.php.net/manual/en/book.dom.php)
  * [GD](https://secure.php.net/manual/en/book.image.php)
  * [Multibyte String](https://secure.php.net/manual/en/book.mbstring.php)
  * [JSON](https://secure.php.net/manual/en/book.json.php)
  

### Installation

1. Download or clone this repository to your server.
2. Point document root of your vhost to the `public` folder.
3. Rewrite all requests to the index.php file.

### Publishing new articles

Publishing new articles is simple. Just upload a new file to your `resources/articles` folder.

It is a good idea to name you article-files starting with a date, e.g.: `2019-01-24-my-article.md` This way the
articles will be correctly ordered in the blog.

Every article is required to include some meta-information and the article itself. This is what an article file
may look like:

```
{
    "title": "Hello World!",
    "metaTitle": "Hello World",
    "description": "A Bloatless Blog sample article",
    "date": "2019-02-17",
    "slug": "hello-world",
    "author": "Bloatless", 
    "categories": "News,Blog,Foo"
}

::METAEND::

This is a sample article.

You can use **markdown syntax** to format your text.
```

It is important that the meta-block and the content are separated by the `::METAEND::` line.

### Dynamic image resizing

Images can be dynamically resized by using a simple URL.

Here's an example: Let's assume there is an image called `myimage.jpg` in your `public/media` folder. The regular
to this image would than be `http://myblog.com/media/myimage.jpg`. If this image should be resized to 100x100px
(e.g. for usage as a thumbnail) it can simply be called using the following URL: `http://myblog.com/image/100x100/media/myimage.jpg`
This will automatically generate a 100x100px thumbnail from the original image. This thumbnail is of course only
generated once and than cached on the server.

### Gallery shortcode

All images from a given folder can be displayed as a gallery within an article using a simple shortcode:

`[gallery folder="/media/my_holiday/"]`

This shortcode will generate thumbnails for all images within the `/public/media/my_holiday` folder and
put a list of those thumbnails in your article. The generated html-code is of course customizable by editing
the `resources/views/partials/gallery.phtml` file.

### Customization

The main configuration (meta data, pages, ...) can be adjusted by simply copying the default config file
`config/config.default.php` to a `config/config.php` file. This file will than be preferred over the default
configuration.

All the template files can be found in the `resources/views` folder. This files can also be copied to another
folder and than be customized. The new folder can be set within the blogs configuration file.

## License

The MIT License

### Libraries used (Thanks!)

* [Parsedown](https://github.com/erusev/parsedown) (MIT License)