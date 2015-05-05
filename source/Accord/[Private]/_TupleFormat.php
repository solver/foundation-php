<?php
// TODO: TupleFormat will be a variation of ListFormat, where every index has a different format. 
//
// A tuple has a fixed length, but we can tolerate optional trailing tuple values by providing defaults in the format,
// which means the output is still of fixed length.
//
// Usage (shows quadruple with one optional element; optionals are always at the end, like optional function args):
// $tupleFormat = (new TupleFormat)->add($fmt)->add($fmt)->add($fmt)->addWithDefault(123, $fmt);
// also add: alist format [name, val, name, val, ...] and tuple-list [x, y, z, x, y, z, x, y, z].