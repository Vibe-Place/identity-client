<?php

namespace Finest\IdentityClient;

use RuntimeException;

/** Not a token identity signed, or not one in date. */
final class InvalidToken extends RuntimeException {}
