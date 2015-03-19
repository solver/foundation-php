<?php
// TODO: An idea for an "object hydrator" layer as a standard DictToObjectFormat class. This allows one to flexibly
// validate the state for an object with a DictFormat tree, and then feed it into a class of their choice:
//
// $dict = (new DictFormat)->...->...->...; // Validates untrusted input.
// $dictToObject = (new DictToObjectFormat)->ofClass(Hydrated::class); // Takes valid dict, produces object of class.
// $hydratorFormat = (new ComposedFormat)->add($dict)->add($dictToObject);
//
// Usage:
// $hydratedObject = $hydratorFormat->extract($untrustedInput, $log);
//
// We should support different strategies for hydration (maybe as separate kinds of DictToObject formats). For example
// the basic injection-via-reflection is one, but unsafe and not flexible. $instance = ClassName::unserialize($data) is
// a better approach as it gives every class full control over how to initialize an object. On the other hand it puts
// more responsibilies on the class and DictToObjectFormat becomes one line of logic, which is pointless (aside from
// implementing the Format interface, which is handy).