# Security Policy

## Supported versions

This package has not yet had a tagged release. Once `v1.0.0` is published,
security fixes will target the latest minor release on the current major
version. Pre-`v1.0.0` tags (`v0.x`) should be treated as unstable and are
not guaranteed to receive backports.

## Reporting a vulnerability

Please do **not** open a public GitHub issue for security vulnerabilities.
Instead, use GitHub's private vulnerability reporting for this repository
(Security tab → "Report a vulnerability"), or contact the maintainer
directly. Include:

- A description of the vulnerability and its impact.
- Steps to reproduce (a minimal Laravel app / test case is ideal).
- The package version (and Laravel version) you tested against.

You should expect an initial response within a few days. This is a
community-maintained package without a dedicated security team or SLA.

## Known, accepted risk areas

Being upfront about these matters more than a blanket "we take security
seriously" — they're deliberate trade-offs, not oversights:

### SVG uploads are disabled by default

SVG is an XML format that can embed `<script>` tags, `<foreignObject>`
content, and event-handler attributes (`onload`, `onerror`, ...). If your
application ever serves an uploaded SVG with a browser-recognized
`Content-Type: image/svg+xml` (or lets a user open it directly), a
malicious SVG can execute script in the context of whoever views it —
a stored XSS vector, and one of the most common real-world file-upload
vulnerabilities in CMS-style products.

This package ships **no SVG sanitizer**. `config('media-library.allowed_mimes')`
and `allowed_mimetypes` do not include `svg` / `image/svg+xml` by default.
If you need SVG support:

1. Add a sanitizer such as [`enshrined/svg-sanitize`](https://github.com/darylldoyle/svg-sanitizer)
   as a dependency of your application.
2. Run every uploaded SVG through it before it's persisted (e.g. by
   extending `MediaController` or listening for the upload, since this
   package does not currently expose a sanitization hook).
3. Only then add `svg` / `image/svg+xml` back to your published
   `config/media-library.php`.

Until you've done that, leave SVG disabled.

### The default policy is intentionally permissive

`Palgoal\MediaLibrary\Policies\MediaPolicy` (the default `policy` in
config) grants `viewAny`/`view`/`create`/`update`/`delete` to **any
authenticated user**, regardless of who uploaded a given file. This is a
deliberate default for single-admin / trusted back-office use cases (the
package's original context), not a safe default for applications with
multiple untrusted users who shouldn't be able to see or delete each
other's uploads.

If your application has multiple untrusted users, publish the config and
either point `media-library.policy` at your own policy class (e.g.
restricting `update`/`delete` to `$media->uploader_id === $user->id`, or
your own role/permission system), or set
`media-library.register_policy = false` and register your own policy
binding.

### `uploader_id` has no foreign key constraint

This is a portability choice (see the migration's docblock), not a bug —
but it does mean the database will not stop you from deleting a user while
their `media` rows still reference that (now-gone) `uploader_id`. Decide
in your own application whether you need to nullify/reassign `uploader_id`
when a user is deleted.

### File upload validation relies on `mimes` + `mimetypes` together

Both Laravel validation rules are applied to every upload: `mimes`
(extension allow-list) and `mimetypes` (server-side content sniffing via
the `fileinfo` extension, not the client-supplied `Content-Type`). Relying
on either one alone is insufficient — a renamed executable can pass an
extension check, and a spoofed `Content-Type` header can pass a naive MIME
check. Do not weaken this by validating only one of the two rules.

### Pre-existing `media` tables

If your application already has a `media` table (from another package or
your own code) before installing this one, this package's migration will
silently skip table creation (`Schema::hasTable()` check) rather than
fail loudly. It does **not** verify that the existing table's columns
match what this package expects. See the migration's docblock and the
README's "Compatibility with an existing `media` table" section.
