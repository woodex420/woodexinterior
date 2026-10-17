// PHP 8.4 CLI on php-wasm for the AI development sandbox (it has no native PHP).
// setup.sh copies this file next to /tmp/phpwasm/node_modules; /tmp/woodex-run/php wraps it.
import { loadNodeRuntime, useHostFilesystem } from '@php-wasm/node';
import { PHP } from '@php-wasm/universal';

const args = process.argv.slice(2);
const runtime = await loadNodeRuntime('8.4', { extensions: ['intl'], emscriptenOptions: { processId: process.pid } });
const php = new PHP(runtime);
useHostFilesystem(php);

const iniDefaults = [
    '-d', 'memory_limit=2G',
    '-d', 'display_errors=stderr',
    '-d', 'error_reporting=E_ALL',
    '-d', 'date.timezone=UTC',
    '-d', 'opcache.enable_cli=0',
    '-d', 'zend.assertions=1',
    // php-wasm cannot spawn processes; without this, Symfony's stty probe hangs the runtime.
    '-d', 'disable_functions=proc_open,popen,exec,shell_exec,system,passthru,pcntl_exec',
];

const response = await php.cli(['php', ...iniDefaults, ...args], { env: { ...process.env }, cwd: process.cwd() });

async function pipe(stream, target) {
    const reader = stream.getReader();
    for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        await new Promise((resolve) => (target.write(value) ? resolve() : target.once('drain', resolve)));
    }
}

await Promise.all([pipe(response.stdout, process.stdout), pipe(response.stderr, process.stderr)]);
const code = await response.exitCode;
process.exitCode = code;
setTimeout(() => process.exit(code), 50);
