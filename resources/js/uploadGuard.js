import { usePage } from '@inertiajs/vue3';

/**
 * Message to show when a picture is bigger than this server accepts, or null when it is fine.
 * PHP silently drops uploads over upload_max_filesize, which otherwise looks like "nothing happened".
 */
export function uploadProblem(file) {
    const limit = usePage().props.uploadLimit?.fileMb;
    if (!file || !limit) return null;
    const mb = file.size / 1048576;
    if (mb <= limit) return null;
    return `"${file.name}" is ${mb.toFixed(1)} MB but this server accepts only ${limit} MB per picture. Use a smaller picture, or raise upload_max_filesize and post_max_size in php.ini.`;
}
