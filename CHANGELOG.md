# Changelog

This is the changelog for the legacy v0.3 release branch.
You should consider upgrading to the v0.4 release branch, originally released 2014-02-02.

## 0.3.5 (2016-12-28)

This is a compatibility release that eases upgrading to the v0.4 release branch.
You should consider upgrading to the v0.4 release branch.

* Feature: Cap min timer interval at 1µs, thus improving compatibility with v0.4
  (#47 by @clue)

## 0.3.4 (2014-03-30)

* Changed StreamSelectLoop to use non-blocking behavior on tick() (@astephens25)

## 0.3.3 (2013-07-08)

* Bug fix: No error on removing non-existent streams (@clue)
* Bug fix: Do not silently remove feof listeners in `LibEvLoop`

## 0.3.0 (2013-04-14)

* BC break: New timers API (@nrk)
* BC break: Remove check on return value from stream callbacks (@nrk)

## 0.2.7 (2013-01-05)

* Bug fix: Fix libevent timers with PHP 5.3
* Bug fix: Fix libevent timer cancellation (@nrk)

## 0.2.6 (2012-12-26)

* Bug fix: Plug memory issue in libevent timers (@cameronjacobson)
* Bug fix: Correctly pause LibEvLoop on stop()

## 0.2.3 (2012-11-14)

* Feature: LibEvLoop, integration of `php-libev`

## 0.2.0 (2012-09-10)

* Version bump

## 0.1.1 (2012-07-12)

* Version bump

## 0.1.0 (2012-07-11)

* First tagged release
