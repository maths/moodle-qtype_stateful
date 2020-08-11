## Index/indexing castext-blocks

The `indexing`-block is a container for the `index`-blocks. Their primary reason for existing is the order-input, but they do have a special role related to the `reveal`-blocks and conditionally rendered numbered lists. The idea is that the `indexing` block defines a potentially named sequence of numbers starting from a given offset and incrementing by one for each visible similarly named `index`-block present within it, it may also define the presentation format for those numbers, ranging from zero-padded to Roman numerals. The `index`-blocks then take that number and display it.

That might seem simple, but the idea is that the numbering will update at the client side should the document be modified by for example `reveal`-blocks revealing something new or any other interaction that might change the length or order of the list of `index`-elements.

Here is an example of a simple numbering using Roman numerals and starting from `5+k` where we assume that `k` is an integer that describes some other listings size, note that the offset is not necessary if it is `1`.

```
[[indexing style='I' start='5+k']]

<p><b>[[index/]])</b> Lorem....</p>

[[if test="foo"]]
<p><b>[[index/]])</b> Lorem....</p>
[[/if]]

<p><b>[[index/]])</b> Lorem....</p>

<p><b>[[index/]])</b> Lorem....</p>

[[if test="foo"]]
<p><b>[[index/]])</b> Lorem....</p>
[[/if]]

[[reveal input="ans1" value="bar"]]
<p><b>[[index/]])</b> Lorem....</p>
[[/reveal]]

[[/indexing]]
```

### Styles

Currently, one might want to try `00`, `000`, `0000`, `1`, `1.`, `I`, ` ` or `?`. Additional ones may be added if need be.


### Future plans

There might be a need for a `step` and possibly for offsets at the `[[index/]]`-level, should offsets appear it would basically mean that some `[[index/]]`-blocks would not eat the current number.
