PHP Redis implementation
==============
Yet another PHP redis implementation.
Raw wrapper for real [Redis] fans. Main advantages:

* Doesn't require any dependencies as all the communication goes via TCP/Unix socket.
* All commands are passed as is, so you have all the freedom to play with Redis just like in redis-cli.
* It won't get deprecated or obsolete. You write raw commands by yourself.
* Doesn't matter which Redis version you have.
* Supports chainable methods. Write multiple commands and send everything at once.
* Custom error function to handle errors.
* Simple and lightweight. All ~600 lines of code are straight forward.
* Forces you to actually learn and understand Redis data structures and commands.

**This fork makes it compatible with Composer and adds a minimal library for PHP 8 type checking.**

## Download
You can checkout latest version with:

    $ git clone git://github.com/thisispiers/PHP-Redis-implementation

## Install

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

- Arminas Zukauskas - arminas@ini.lt
- thisispiers

*Based on http://redis.io/topics/protocol*

## License

[MIT] Do whatever you want, attribution is nice but not required

[Redis]: https://redis.io
[phpunit]: https://phpunit.de/
[https://redis.io/commands]: https://redis.io/commands
[mit]: https://tldrlegal.com/license/mit-license
