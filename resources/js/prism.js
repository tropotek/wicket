// Code highlighting for page content; loaded only on pages that contain code blocks.
// Language set as tkwiki's Prism build.
import Prism from 'prismjs';
import 'prismjs/components/prism-markup-templating';
import 'prismjs/components/prism-clike';
import 'prismjs/components/prism-javascript';
// Components extend each other: keep dependencies first (c → cpp → arduino).
import 'prismjs/components/prism-c';
import 'prismjs/components/prism-cpp';
import 'prismjs/components/prism-arduino';
import 'prismjs/components/prism-bash';
import 'prismjs/components/prism-batch';
import 'prismjs/components/prism-java';
import 'prismjs/components/prism-json';
import 'prismjs/components/prism-php';
import 'prismjs/components/prism-python';
import 'prismjs/components/prism-scss';
import 'prismjs/components/prism-sql';
import 'prismjs/plugins/line-numbers/prism-line-numbers';
import '../scss/prism.scss';

Prism.highlightAll();
