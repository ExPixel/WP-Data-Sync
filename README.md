WP Data Sync
===

Small WordPress plugin that I had to write to synchronize the data inside of HTML tags on multiple pages.

**WARNING:** This thing is kind of just cobbled together so you have two data-ep-dsync attributes one one page with the same content you might enter an infinite loop.

Example
---

Page 1:
```html
<table>
    <tr>
        <td data-ep-dsync="cool-column">$10.00 (sync)</td>
    </tr>
</table>
```

Page 2:
```html
<table>
    <tr>
        <td>Not Synchronized</td>
    </tr>
    <tr>
        <td data-ep-dsync="cool-column">$10.00 (sync)</td>
    </tr>
</table>
```
