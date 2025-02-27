<?php
/**
 * Creates functions for all classes meant to be instantiated in client code to allow for constructor method chaining.
 *
 * @copyright Copyright (c) 2008 Will Bond
 *
 * @author Will Bond [wb] <will@flourishlib.com>
 *
 * @license http://flourishlib.com/license
 */

/**
 * @param null|string $date
 * @return fDate
 */
function fDate($date = null)
{
    return new fDate($date);
}

/**
 * @param string $directory
 * @return fDirectory
 */
function fDirectory($directory)
{
    return new fDirectory($directory);
}

/**
 * @param string $file
 * @return fFile
 */
function fFile($file)
{
    return new fFile($file);
}

/**
 * @param string
 * @return fImage
 */
function fImage($file_path)
{
    return new fImage($file_path);
}

/**
 * @param float|int|null $amount
 * @param string|null $currency
 * @return fMoney
 */
function fMoney($amount, $currency = null)
{
    return new fMoney($amount, $currency);
}

/**
 * @param int|null $value
 * @param int|null $scale
 * @return fNumber
 */
function fNumber($value, $scale = null)
{
    return new fNumber($value, $scale);
}

/**
 * @param null|string $time
 * @return fTime
 */
function fTime($time = null)
{
    return new fTime($time);
}

/**
 * @param null|string $datetime
 * @param null|string $timezone
 * @return fTimestamp
 */
function fTimestamp($datetime = null, $timezone = null)
{
    return new fTimestamp($datetime, $timezone);
}

/*
 * Copyright (c) 2008 Will Bond <will@flourishlib.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
