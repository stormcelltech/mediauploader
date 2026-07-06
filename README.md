# Laravel MediaUploader

A premium, lightweight, self-contained media management and file uploading ecosystem for Laravel. It provides a modular backend combined with a vanilla JavaScript drag-and-drop client interface styled with Tailwind CSS.

---

## Features

- **Zero-Build Overhead:** Clean vanilla JavaScript frontend engine—no heavy framework compile dependencies.
- **Automated Lifecycle Management:** Seamless Artisan command handles asset distribution and migration sync blocks.
- **Web Session Authentication:** Securely routes communications over `web` middleware stacks to leverage native session state and absolute CSRF verification.
- **Smart State Persistence:** Utilizes a persistent hidden input element that tracks database primary media record IDs rather than temporary file strings.
- **Polished UX Matrix:** Includes drag-and-drop bindings, image preview resolution, modal management, and micro-animations out-of-the-box.

---

## Installation

### 1. Require the Package via Composer

Pull the core repository down into your application ecosystem:

```bash
composer require stormcelltech/mediauploader
```

## 2. Structural Engine Implementations

### Single Resource Hydration Layout (Profile / Icon / Document)

To initialize a file selection box that updates a single database column (e.g. `logo_media_id`), bind an absolute structural integer primary key to the initialization state:

```html
<x-media-upload::uploader
    id="logo-uploader"
    name="logo_id"
    :value="$settings->logo_id ?? null"
    type="single"
    text="Upload Logo"
/>
```

# Configuration Data Attributes Matrix

This technical reference outlines the absolute data attribute API schema required by the `FileUploader.js` client engine. The runtime engine parses these attributes to build the component state, handle database hydration, and toggle UI states.

---

## 3. Core Specification Matrix

The following parameters must be declared as standard HTML5 attributes on the container DOM element designated with the `class="uploader"` marker:

| Attribute Name       | Expected Data Type | Required | Default Value   | Functional Impact & Component Scope                                                                                                                                             |
| :------------------- | :----------------- | :------: | :-------------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `id`                 | `String`           | **Yes**  | _None_          | Unique DOM layout identifier. Used by the internal runtime registry (`FileUploader.instances`) to manage scoped event dispatching.                                              |
| `data-fileinputname` | `String`           | **Yes**  | _None_          | Dictates the `name` attribute value applied to the hidden HTML `<input>` tag that stores the database entity ID.                                                                |
| `data-hasfile`       | `Integer\|Array`   |    No    | `null`          | **Hydration Value:** Holds the primary database integer ID of the asset (or a JSON-serialized array of IDs for multi-mode). Prompts the engine to pull matching asset metadata. |
| `data-uploadtype`    | `String`           |    No    | `'single'`      | Structurally controls the file array constraints. Supported tokens are `'single'` or `'multiple'`.                                                                              |
| `data-uploadtext`    | `String`           |    No    | `'Select File'` | UI Typography Override. Customizes the main text string value rendered inside the primary drag-and-drop landing action wrapper.                                                 |
| `data-preview`       | `String`           |    No    | `'true'`        | Textual boolean toggle (`'true'`/`'false'`). Dictates whether file thumbnail blocks and interactive image modules render inside the workspace.                                  |
| `data-hidemediatab`  | `String`           |    No    | `'false'`       | Textual boolean toggle (`'true'`/`'false'`). When `'true'`, strips out the historical file library browser, restricting the interface to fresh file uploads only.               |

---

<div id="avatar-uploader"
     class="uploader"
     data-fileinputname="avatar_id"
     data-hasfile="142"
     data-uploadtype="single"
     data-uploadtext="Upload Profile Photo"
     data-preview="true"
     data-hidemediatab="false">
</div>
