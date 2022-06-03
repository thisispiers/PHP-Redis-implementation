PHP Redis implementation
==============
Yet another php redis implementation.
Raw wrapper for real [Redis] fans. Main advantages:

* Doesn't require any dependencies as all the communication goes via tcp socket.
* All commands are passed as is, so you have all the freedom to play with redis just like in redis-cli.
* It won't get deprecated or obsolete for the same reason. You write raw commands by yourself.
* Doesn't matter which redis version you have.
* Supports chainable methods. Write multiple commands and send everything at once.
* Custom error function to handle errors.
* Dead Simple and lightweight, you're welcome to read all the 300+ lines of redis.php
* Forces you to actually learn and understand redis data structures and commands.

**This fork makes it compatible with Composer and adds a minimal library for PHP 8 type checking.**

## Download
You can checkout latest version with:

    $ git clone git://github.com/thisispiers/PHP-Redis-implementation


## Install
To install PHP redis:

```
composer require thisispiers/php-redis-implementation
```

## Contributing

1. Fork it!
2. Create your feature branch: `git checkout -b my-new-feature`
3. Commit your changes: `git commit -am 'Add some feature'`
4. Push to the branch: `git push origin my-new-feature`
5. Submit a pull request

## Author
Arminas Zukauskas - arminas@ini.lt
thisispiers

*Based on http://redis.io/topics/protocol*

## License

[MIT] Do whatever you want, attribution is nice but not required

[Redis]: https://redis.io
[phpunit]: https://phpunit.de/
[https://redis.io/commands]: https://redis.io/commands
[mit]: https://tldrlegal.com/license/mit-license
