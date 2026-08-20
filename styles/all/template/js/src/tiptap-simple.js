import { Editor, Extension, Mark, Node } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import TextStyle from '@tiptap/extension-text-style';
import Blockquote from '@tiptap/extension-blockquote';
import CodeBlock from '@tiptap/extension-code-block';
import Paragraph from '@tiptap/extension-paragraph';
import Color from '@tiptap/extension-color';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import CharacterCount from '@tiptap/extension-character-count';
import TextAlign from '@tiptap/extension-text-align';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';
import Highlight from '@tiptap/extension-highlight';


// Expose the global integration registry for other extensions
window.phpbbWysiwyg = window.phpbbWysiwyg || {
	extensions: [],
	buttons: [],
	instances: new Map(),
	registerExtension(ext) {
		this.extensions.push(ext);
	},
	registerButton(btn) {
		this.buttons.push(btn);
	},
	attach(textarea, options) {
		if (typeof window.__phpbbWysiwygAttach === 'function') {
			return window.__phpbbWysiwygAttach(textarea, options);
		}
	},
	scan(rootNode) {
		if (typeof window.__phpbbWysiwygScan === 'function') {
			return window.__phpbbWysiwygScan(rootNode);
		}
	}
};

document.addEventListener('DOMContentLoaded', () => {
	// Universal selector for any phpBB posting area, quickreply, ACP or 3rd-party extension editor
	const UNIVERSAL_SELECTOR = [
		'textarea[data-wysiwyg="true"]',
		'textarea[data-bbcode="true"]',
		'form#postform textarea[name="message"]',
		'form#qr_postform textarea[name="message"]',
		'form#ucp textarea[name="signature"]',
		'textarea#message',
		'textarea#signature',
		'textarea[name="message"]',
		'textarea[name="signature"]',
		'.message-box textarea',
		'#message-box textarea'
	].join(', ');

	// Load translations from template variables
	let translations = {};
	const container = document.getElementById('wysiwyg-editor-container');
	if (container) {
		try {
			translations = JSON.parse(container.getAttribute('data-lang') || '{}');
		} catch (e) {
			console.error('Failed to parse WYSIWYG language JSON', e);
		}
	}

	const htmlToBbcodeUrl = container ? container.getAttribute('data-html-to-bbcode-url') : '';
	const bbcodeToHtmlUrl = container ? container.getAttribute('data-bbcode-to-html-url') : '';
	const allowToggleGlobal = container ? (container.getAttribute('data-toggle-enabled') === '1') : true;
	const minChars = container ? parseInt(container.getAttribute('data-min-chars') || '1', 10) : 1;
	const maxChars = container ? parseInt(container.getAttribute('data-max-chars') || '0', 10) : 0;

	let customBbcodes = [];
	if (container) {
		try {
			customBbcodes = JSON.parse(container.getAttribute('data-custom-bbcodes') || '[]');
		} catch (e) {
			console.error('Failed to parse custom BBCodes JSON', e);
		}
	}

	// Helper to retrieve translated string from phpBB language system
	function lang(key) {
		return (translations && typeof translations[key] !== 'undefined') ? translations[key] : key;
	}

	function scan(rootNode = document) {
		const scope = (rootNode && rootNode.querySelectorAll) ? rootNode : document;
		const candidates = scope.querySelectorAll(UNIVERSAL_SELECTOR);
		candidates.forEach(ta => {
			if (ta && !ta.disabled && !ta.dataset.wysiwygInitialized && ta.dataset.wysiwygIgnore !== 'true') {
				attach(ta);
			}
		});
	}

	function attach(textarea, options = {}) {
		if (!textarea || textarea.dataset.wysiwygInitialized === 'true') {
			return;
		}

		// Ensure textarea has an id if it's the primary message field
		if (!textarea.id && textarea.name === 'message') {
			textarea.id = 'message';
		}

		textarea.dataset.wysiwygInitialized = 'true';
		document.body.classList.add('wysiwyg-active');

		const isAcp = options.isAcp || (textarea.getAttribute('data-bbcode') === 'true' && (!textarea.name || !textarea.name.includes('message')));
		const initialContentTextarea = document.getElementById('wysiwyg-initial-content');
		const initialHtml = options.initialHtml || (initialContentTextarea ? initialContentTextarea.value : (container ? (container.getAttribute('data-initial-content') || '') : ''));

		initWysiwyg({
			element: textarea,
			initialHtml: isAcp ? null : initialHtml,
			type: isAcp ? 'acp' : 'frontend'
		});
	}

	window.__phpbbWysiwygAttach = attach;
	window.__phpbbWysiwygScan = scan;

	// Initial scan
	scan(document);

	// Setup lightweight MutationObserver for dynamically injected forms (AJAX modais, quick edits, etc.)
	if (window.MutationObserver) {
		const observer = new window.MutationObserver(mutations => {
			for (const mutation of mutations) {
				if (mutation.addedNodes && mutation.addedNodes.length > 0) {
					for (const node of mutation.addedNodes) {
						if (node.nodeType === 1) { // Element node
							if (node.matches && node.matches(UNIVERSAL_SELECTOR)) {
								attach(node);
							} else if (node.querySelectorAll) {
								scan(node);
							}
						}
					}
				}
			}
		});
		observer.observe(document.body, { childList: true, subtree: true });
	}

	// Listen for manual scan custom event
	document.addEventListener('phpbbWysiwygScan', e => {
		scan(e.detail && e.detail.root ? e.detail.root : document);
	});

	function initWysiwyg(target) {
		const textarea = target.element;
		const form = textarea.form;
		if (!form) return;

		// Create dynamic wrapper container
		const editorContainer = document.createElement('div');
		editorContainer.className = 'wysiwyg-editor-container-wrapper';
		textarea.parentNode.insertBefore(editorContainer, textarea.nextSibling);

		// Hide original textarea
		textarea.style.display = 'none';

		// Set flag that wysiwyg was used
		let wysiwygUsed = form.querySelector('input[name="wysiwyg_used"]');
		if (!wysiwygUsed) {
			wysiwygUsed = document.createElement('input');
			wysiwygUsed.type = 'hidden';
			wysiwygUsed.name = 'wysiwyg_used';
			wysiwygUsed.value = '1';
			form.appendChild(wysiwygUsed);
		}

		if (target.initialHtml !== null) {
			bootEditor(editorContainer, textarea, wysiwygUsed, target.initialHtml, form);
		} else {
			// Fetch via AJAX for ACP textareas
			const bbcode = textarea.value;
			const formData = new FormData();
			formData.append('action', 'bbcode_to_html');
			formData.append('bbcode', bbcode);

			fetch(bbcodeToHtmlUrl || form.action || window.location.href, {
				method: 'POST',
				body: formData
			})
			.then(res => res.json())
			.then(data => {
				if (data.html !== undefined) {
					bootEditor(editorContainer, textarea, wysiwygUsed, data.html, form);
				} else {
					textarea.style.display = 'block';
					editorContainer.remove();
				}
			})
			.catch(() => {
				textarea.style.display = 'block';
				editorContainer.remove();
			});
		}
	}

	function bootEditor(container, textarea, wysiwygUsed, htmlContent, form) {
		const wrapper = document.createElement('div');
		wrapper.className = 'wysiwyg-editor-wrapper inputbox';

		const toolbarEl = document.createElement('div');
		toolbarEl.className = 'wysiwyg-toolbar';
		toolbarEl.setAttribute('role', 'toolbar');
		toolbarEl.setAttribute('aria-label', lang('WYSIWYG_TOOLBAR'));

		let customBbcodesRow = null;
		if (typeof customBbcodes !== 'undefined' && customBbcodes.length > 0) {
			customBbcodesRow = document.createElement('div');
			customBbcodesRow.className = 'wysiwyg-toolbar-row wysiwyg-toolbar-custom-bbcodes';
			customBbcodesRow.setAttribute('role', 'toolbar');
			customBbcodesRow.setAttribute('aria-label', lang('WYSIWYG_CUSTOM_BBCODES'));
		}

		const contentEl = document.createElement('div');
		contentEl.className = 'wysiwyg-content-area';

		const footerEl = document.createElement('div');
		footerEl.className = 'wysiwyg-footer';
		const charCountEl = document.createElement('span');
		charCountEl.className = 'wysiwyg-char-count';
		charCountEl.textContent = lang('WYSIWYG_CHARACTERS').replace('%d', '0');
		footerEl.appendChild(charCountEl);

		wrapper.appendChild(toolbarEl);
		if (customBbcodesRow) {
			wrapper.appendChild(customBbcodesRow);
		}
		wrapper.appendChild(contentEl);
		wrapper.appendChild(footerEl);
		container.appendChild(wrapper);

		// Setup custom size extension
		const CustomFontSize = Mark.create({
			name: 'fontSize',
			addAttributes() {
				return {
					fontSize: {
						default: null,
						parseHTML: element => element.style.fontSize || element.getAttribute('data-bbcode-val'),
						renderHTML: attributes => {
							if (!attributes.fontSize) return {};
							const val = String(attributes.fontSize).replace('%', '');
							return {
								'data-bbcode': 'size',
								'data-bbcode-val': val,
								style: `font-size: ${val}%`,
							};
						},
					},
				};
			},
			parseHTML() {
				return [
					{
						tag: 'span[data-bbcode="size"]',
					},
					{
						style: 'font-size',
					}
				];
			},
			renderHTML({ HTMLAttributes }) {
				return ['span', HTMLAttributes, 0];
			},
			addCommands() {
				return {
					setFontSize: fontSize => ({ chain }) => {
						const val = String(fontSize).replace('%', '');
						return chain().setMark('fontSize', { fontSize: val }).run();
					},
					unsetFontSize: () => ({ chain }) => {
						return chain().unsetMark('fontSize').run();
					},
				};
			},
		});

		// Setup custom smiley extension
		const CustomSmiley = Image.extend({
			name: 'smiley',
			addAttributes() {
				return {
					...this.parent?.(),
					smiley: {
						default: null,
						parseHTML: element => element.getAttribute('data-smiley'),
						renderHTML: attributes => {
							if (!attributes.smiley) return {};
							return {
								'data-smiley': attributes.smiley,
								class: 'smiley'
							};
						},
					},
				};
			},
			parseHTML() {
				return [
					{
						tag: 'img[data-smiley]',
					},
				];
			},
		});

		// Setup custom BBCode schema preservation extension
		const CustomBBCode = Mark.create({
			name: 'customBBCode',
			addAttributes() {
				return {
					bbcode: {
						default: null,
						parseHTML: element => element.getAttribute('data-bbcode'),
						renderHTML: attributes => {
							if (!attributes.bbcode) return {};
							return { 'data-bbcode': attributes.bbcode };
						},
					},
					bbcodeVal: {
						default: null,
						parseHTML: element => element.getAttribute('data-bbcode-val'),
						renderHTML: attributes => {
							if (!attributes.bbcodeVal) return {};
							return { 'data-bbcode-val': attributes.bbcodeVal };
						},
					},
					bbcodeAttrs: {
						default: null,
						parseHTML: element => element.getAttribute('data-bbcode-attrs'),
						renderHTML: attributes => {
							if (!attributes.bbcodeAttrs) return {};
							return { 'data-bbcode-attrs': attributes.bbcodeAttrs };
						},
					},
				};
			},
			parseHTML() {
				return [
					{
						tag: 'span[data-bbcode]',
					},
				];
			},
			renderHTML({ HTMLAttributes }) {
				return ['span', { ...HTMLAttributes, class: 'wysiwyg-custom-bbcode' }, 0];
			},
			addCommands() {
				return {
					toggleCustomBBCode: attributes => ({ commands }) => {
						return commands.toggleMark(this.name, attributes);
					},
					setCustomBBCode: attributes => ({ commands }) => {
						return commands.setMark(this.name, attributes);
					},
					unsetCustomBBCode: () => ({ commands }) => {
						return commands.unsetMark(this.name);
					},
				};
			},
		});

		// Setup custom Attachment extension
		const AttachmentNode = Node.create({
			name: 'attachment',
			group: 'block',
			atom: true,
			addAttributes() {
				return {
					id: {
						default: null,
						parseHTML: element => element.getAttribute('data-bbcode-val'),
						renderHTML: attributes => {
							if (!attributes.id) return {};
							return {
								'data-bbcode': 'attachment',
								'data-bbcode-val': attributes.id
							};
						},
					},
					filename: {
						default: null,
						parseHTML: element => element.getAttribute('data-filename'),
						renderHTML: attributes => {
							if (!attributes.filename) return {};
							return { 'data-filename': attributes.filename };
						},
					},
				};
			},
			parseHTML() {
				return [{ tag: 'div[data-bbcode="attachment"]' }];
			},
			renderHTML({ HTMLAttributes }) {
				return [
					'div',
					{ ...HTMLAttributes, class: 'wysiwyg-attachment' },
					HTMLAttributes['data-filename'] || lang('WYSIWYG_ATTACHMENT')
				];
			},
		});

		// Setup custom Paragraph extension to preserve whitespace
		const CustomParagraph = Paragraph.extend({
			parseHTML() {
				return [
					{
						tag: 'p',
						preserveWhitespace: 'full',
					},
				];
			},
		});

		// Setup custom Blockquote extension
		const CustomBlockquote = Blockquote.extend({
			name: 'blockquote',
			group: 'block',
			content: 'block+',
			defining: true,
			addAttributes() {
				return {
					author: {
						default: null,
						parseHTML: element => element.getAttribute('data-author') || element.getAttribute('author'),
						renderHTML: attributes => {
							if (!attributes.author) return {};
							return { 'data-author': attributes.author };
						}
					},
					postId: {
						default: null,
						parseHTML: element => element.getAttribute('data-post-id'),
						renderHTML: attributes => {
							if (!attributes.postId) return {};
							return { 'data-post-id': attributes.postId };
						}
					},
					time: {
						default: null,
						parseHTML: element => element.getAttribute('data-time'),
						renderHTML: attributes => {
							if (!attributes.time) return {};
							return { 'data-time': attributes.time };
						}
					},
					userId: {
						default: null,
						parseHTML: element => element.getAttribute('data-user-id'),
						renderHTML: attributes => {
							if (!attributes.userId) return {};
							return { 'data-user-id': attributes.userId };
						}
					}
				};
			},
			parseHTML() {
				return [
					{
						tag: 'blockquote',
						contentElement: dom => dom.querySelector('.quote-content') || dom.querySelector('div > div') || dom.querySelector('div') || dom,
						getAttrs: dom => {
							let author = dom.getAttribute('data-author') || dom.getAttribute('author');
							const postId = dom.getAttribute('data-post-id');
							const time = dom.getAttribute('data-time');
							const userId = dom.getAttribute('data-user-id');
							const cite = dom.querySelector('cite');
							if (cite) {
								if (!author) {
									const text = cite.textContent.trim();
									const lastColon = text.lastIndexOf(':');
									const cleanText = lastColon !== -1 ? text.substring(0, lastColon) : text;
									const wroteWord = lang('WYSIWYG_WROTE');
									const matchIndex = cleanText.toLowerCase().lastIndexOf(' ' + wroteWord.toLowerCase());
									if (matchIndex !== -1) {
										author = cleanText.substring(0, matchIndex).trim();
									} else {
										// Language-agnostic fallback: strip the last word (which is the "wrote" verb in the quote's original language)
										const words = cleanText.trim().split(/\s+/);
										if (words.length > 1) {
											words.pop();
											author = words.join(' ');
										} else {
											author = cleanText.trim();
										}
									}
								}
								if (cite.parentNode) {
									cite.parentNode.removeChild(cite);
								}
							}
							return {
								author: author || null,
								postId: postId || null,
								time: time || null,
								userId: userId || null
							};
						}
					}
				];
			},
			renderHTML({ node, HTMLAttributes }) {
				const author = node.attrs.author;
				if (author) {
					const wrote = lang('WYSIWYG_WROTE');
					return [
						'blockquote',
						{ ...HTMLAttributes, 'data-author': author },
						[
							'div',
							{},
							['cite', {}, `${author} ${wrote}:`],
							['div', { class: 'quote-content' }, 0]
						]
					];
				}
				return [
					'blockquote',
					{ ...HTMLAttributes, class: 'uncited' },
					[
						'div',
						{},
						['div', { class: 'quote-content' }, 0]
					]
				];
			}
		});

		// Setup custom CodeBlock extension
		const CustomCodeBlock = CodeBlock.extend({
			name: 'codeBlock',
			group: 'block',
			content: 'text*',
			marks: '',
			code: true,
			defining: true,
			parseHTML() {
				return [
					{
						tag: 'div.codebox',
						contentElement: dom => dom.querySelector('code') || dom,
						preserveWhitespace: 'full',
					},
					{
						tag: 'pre',
						contentElement: dom => dom.querySelector('code') || dom,
						preserveWhitespace: 'full',
					}
				];
			},
			renderHTML({ HTMLAttributes }) {
				const selectAllText = lang('WYSIWYG_SELECT_ALL_CODE');
				const codeText = lang('WYSIWYG_CODE_LABEL');
				return [
					'div',
					{ class: 'codebox' },
					[
						'p',
						{},
						`${codeText}: `,
						[
							'a',
							{
								href: '#',
								onclick: 'selectCode(this); return false;'
							},
							selectAllText
						]
					],
					[
						'pre',
						HTMLAttributes,
						['code', {}, 0]
					]
				];
			}
		});

		// Setup custom Spoiler extension
		const SpoilerNode = Node.create({
			name: 'spoiler',
			group: 'block',
			content: 'block+',
			defining: true,
			parseHTML() {
				return [{ tag: 'details[data-bbcode="spoiler"]' }];
			},
			renderHTML() {
				return [
					'details',
					{ 'data-bbcode': 'spoiler' },
					['summary', {}, lang('WYSIWYG_SPOILER')],
					0
				];
			},
		});

		// Setup keyboard shortcuts extension
		const KeyboardShortcutsExtension = Extension.create({
			name: 'keyboardShortcuts',
			addKeyboardShortcuts() {
				return {
					'Mod-k': () => {
						const previousUrl = this.editor.getAttributes('link').href;
						const url = window.prompt(lang('WYSIWYG_PROMPT_URL'), previousUrl);
						if (url === null) return true;
						if (url === '') {
							this.editor.chain().focus().extendMarkRange('link').unsetLink().run();
							return true;
						}
						const cleanUrl = !/^(https?:\/\/|mailto:|ftp:\/\/|\/|#)/i.test(url) ? `https://${url}` : url;
						this.editor.chain().focus().extendMarkRange('link').setLink({ href: cleanUrl }).run();
						return true;
					},
					'Mod-Shift-7': () => this.editor.chain().focus().toggleOrderedList().run(),
					'Mod-Shift-8': () => this.editor.chain().focus().toggleBulletList().run(),
					'Mod-Shift-9': () => this.editor.chain().focus().toggleBlockquote().run(),
					'Mod-Alt-c': () => this.editor.chain().focus().toggleCodeBlock().run(),
					'Mod-Enter': () => {
						if (!validateContentLength(this.editor, isSourceMode)) {
							return true;
						}
						if (form) {
							const submitBtn = form.querySelector('input[type="submit"][name="post"], input[type="submit"][name="submit"], button[type="submit"][name="post"]');
							if (submitBtn) {
								submitBtn.click();
							} else if (typeof form.requestSubmit === 'function') {
								form.requestSubmit();
							} else {
								form.submit();
							}
						}
						return true;
					},
				};
			},
		});

		// Initialize Editor
		const editor = new Editor({
			element: contentEl,
			extensions: [
				StarterKit.configure({
					paragraph: false,
					blockquote: false,
					codeBlock: false,
				}),
				CustomParagraph,
				CustomBlockquote,
				CustomCodeBlock,
				Underline,
				Link.configure({ openOnClick: false, autolink: true }),
				Image,
				TextStyle,
				Color,
				CustomFontSize,
				CustomSmiley,
				CustomBBCode,
				AttachmentNode,
				SpoilerNode,
				KeyboardShortcutsExtension,
				Table.configure({
					HTMLAttributes: {
						'data-bbcode': 'table',
					},
				}),
				TableRow.configure({
					HTMLAttributes: {
						'data-bbcode': 'tr',
					},
				}),
				TableCell.configure({
					HTMLAttributes: {
						'data-bbcode': 'td',
					},
				}),
				TableHeader,
				CharacterCount,
				TextAlign.configure({
					types: ['heading', 'paragraph'],
				}),
				Superscript,
				Subscript,
				Highlight.extend({
					parseHTML() {
						return [
							{ tag: 'mark' },
							{ tag: 'span[data-bbcode="highlight"]' }
						];
					},
					renderHTML({ HTMLAttributes }) {
						return ['mark', { ...HTMLAttributes, 'data-bbcode': 'highlight' }, 0];
					}
				}),
				...window.phpbbWysiwyg.extensions,
			],
			content: htmlContent,
			editorProps: {
				attributes: {
					role: 'textbox',
					'aria-multiline': 'true',
					'aria-label': lang('WYSIWYG_CONTENT_AREA'),
				},
				handleKeyDown: (view, event) => {
					// Ctrl+Enter or Cmd+Enter: Submit posting form
					if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
						event.preventDefault();
						if (form) {
							if (wysiwygUsed.value === '1') {
								textarea.value = editor.getHTML();
							}
							const submitBtn = form.querySelector('input[type="submit"][name="post"]') ||
								form.querySelector('input[type="submit"][name="submit"]') ||
								form.querySelector('button[type="submit"][name="post"]') ||
								form.querySelector('button[type="submit"]');
							if (submitBtn) {
								submitBtn.click();
							} else {
								form.submit();
							}
						}
						return true;
					}
					return false;
				},
				handlePaste: (view, event) => {
					// Smart URL paste: If text is selected and clipboard contains a single URL, wrap selection in link
					const clipboardText = (event.clipboardData || window.clipboardData)?.getData('text/plain')?.trim();
					if (clipboardText && /^https?:\/\/[^\s]+$/i.test(clipboardText)) {
						const { state } = view;
						const { selection } = state;
						if (!selection.empty) {
							event.preventDefault();
							editor.chain().focus().setLink({ href: clipboardText }).run();
							return true;
						}
					}
					return false;
				},
			},
			onUpdate: ({ editor: currentEditor }) => {
				if (wysiwygUsed.value === '1') {
					textarea.value = currentEditor.getHTML();
				}
				// Update character count
				const count = currentEditor.storage.characterCount.characters();
				charCountEl.textContent = lang('WYSIWYG_CHARACTERS').replace('%d', count);
			},
			onSelectionUpdate: () => {
				updateToolbarActiveStates();
			},
		});

		// Initial sync
		textarea.value = editor.getHTML();
		const initialCount = editor.storage.characterCount.characters();
		charCountEl.textContent = lang('WYSIWYG_CHARACTERS').replace('%d', initialCount);

		// Synchronize on form submit and validate post length
		function validateContentLength(currentEditor, isSource = false) {
			if (isSource) {
				const text = textarea.value.trim();
				if (text.length < minChars) {
					showMinLengthError(text.length);
					return false;
				}
				if (maxChars > 0 && text.length > maxChars) {
					showMaxLengthError(text.length);
					return false;
				}
				return true;
			}

			const text = currentEditor.getText().trim();
			let hasNonTextNodes = false;
			currentEditor.state.doc.descendants(node => {
				if (['image', 'attachment', 'table', 'customSmiley'].includes(node.type.name)) {
					hasNonTextNodes = true;
					return false;
				}
				return true;
			});

			if (!hasNonTextNodes && text.length < minChars) {
				showMinLengthError(text.length);
				currentEditor.chain().focus().run();
				return false;
			}

			if (maxChars > 0 && text.length > maxChars) {
				showMaxLengthError(text.length);
				currentEditor.chain().focus().run();
				return false;
			}

			return true;
		}

		function showMinLengthError(currentCount) {
			let errorMsg = lang('WYSIWYG_TOO_FEW_CHARS');
			const limitTemplate = lang('WYSIWYG_TOO_FEW_CHARS_LIMIT');
			if (limitTemplate && limitTemplate !== 'WYSIWYG_TOO_FEW_CHARS_LIMIT') {
				errorMsg = limitTemplate
					.replace(/%1\$d/g, currentCount)
					.replace(/%2\$d/g, minChars)
					.replace(/%d/g, minChars);
			}
			window.alert(errorMsg);
		}

		function showMaxLengthError(currentCount) {
			let errorMsg = lang('WYSIWYG_TOO_MANY_CHARS');
			const limitTemplate = lang('WYSIWYG_TOO_MANY_CHARS_LIMIT');
			if (limitTemplate && limitTemplate !== 'WYSIWYG_TOO_MANY_CHARS_LIMIT') {
				errorMsg = limitTemplate
					.replace(/%1\$d/g, currentCount)
					.replace(/%2\$d/g, maxChars)
					.replace(/%d/g, maxChars);
			}
			window.alert(errorMsg);
		}

		if (form && form.addEventListener) {
			form.addEventListener('submit', e => {
				if (wysiwygUsed.value === '1') {
					if (!isSourceMode) {
						textarea.value = editor.getHTML();
					}
					const submitter = e.submitter;
					const isPreviewOrDraft = submitter && (submitter.name === 'preview' || submitter.name === 'save');
					if (!isPreviewOrDraft && !validateContentLength(editor, isSourceMode)) {
						e.preventDefault();
						e.stopPropagation();
						return false;
					}
				}
			});
		}

		// Helper to close all dropdown menus
		function closeAllMenus() {
			headingMenu.style.display = 'none';
			headingBtn.setAttribute('aria-expanded', 'false');
			listMenu.style.display = 'none';
			listBtn.setAttribute('aria-expanded', 'false');
			sizeMenu.style.display = 'none';
			sizeBtn.setAttribute('aria-expanded', 'false');
			colorPalette.style.display = 'none';
			colorBtn.setAttribute('aria-expanded', 'false');
			tableMenu.style.display = 'none';
			tableBtn.setAttribute('aria-expanded', 'false');
		}

		// Close menus on outside click or Escape key
		document.addEventListener('click', () => {
			closeAllMenus();
		});
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') {
				closeAllMenus();
			}
		});

		// 1. Heading Dropdown Menu
		const headingContainer = document.createElement('div');
		headingContainer.className = 'wysiwyg-dropdown select-heading';

		const headingBtn = document.createElement('button');
		headingBtn.type = 'button';
		headingBtn.className = 'wysiwyg-btn btn-heading-toggle';
		headingBtn.title = lang('WYSIWYG_HEADING');
		headingBtn.setAttribute('aria-label', lang('WYSIWYG_HEADING'));
		headingBtn.setAttribute('aria-haspopup', 'true');
		headingBtn.setAttribute('aria-expanded', 'false');
		headingBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 19h-2v-6h-6v6H9V5h2v6h6V5h2v14z"/></svg><span class="wysiwyg-caret"></span>';

		const headingMenu = document.createElement('div');
		headingMenu.className = 'wysiwyg-dropdown-menu';

		const headings = [
			{ level: 0, label: lang('WYSIWYG_HEADING_P') },
			{ level: 1, label: lang('WYSIWYG_HEADING_1') },
			{ level: 2, label: lang('WYSIWYG_HEADING_2') },
			{ level: 3, label: lang('WYSIWYG_HEADING_3') },
			{ level: 4, label: lang('WYSIWYG_HEADING_4') },
		];

		headings.forEach(h => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = h.label;
			item.setAttribute('role', 'button');
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (h.level === 0) {
					editor.chain().focus().setParagraph().run();
				} else {
					editor.chain().focus().toggleHeading({ level: h.level }).run();
				}
				closeAllMenus();
			});
			headingMenu.appendChild(item);
		});
		headingBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = headingMenu.style.display === 'block';
			closeAllMenus();
			if (!isVisible) {
				headingMenu.style.display = 'block';
				headingBtn.setAttribute('aria-expanded', 'true');
			}
		});
		headingContainer.appendChild(headingBtn);
		headingContainer.appendChild(headingMenu);

		// 2. List Dropdown Menu
		const listContainer = document.createElement('div');
		listContainer.className = 'wysiwyg-dropdown select-lists';

		const listBtn = document.createElement('button');
		listBtn.type = 'button';
		listBtn.className = 'wysiwyg-btn btn-list-toggle';
		listBtn.title = lang('WYSIWYG_LISTS');
		listBtn.setAttribute('aria-label', lang('WYSIWYG_LISTS'));
		listBtn.setAttribute('aria-haspopup', 'true');
		listBtn.setAttribute('aria-expanded', 'false');
		listBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0 12c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg><span class="wysiwyg-caret"></span>';

		const listMenu = document.createElement('div');
		listMenu.className = 'wysiwyg-dropdown-menu';

		const lists = [
			{ name: lang('WYSIWYG_LIST_NONE'), value: 'none' },
			{ name: lang('WYSIWYG_LIST_BULLET'), value: 'bullet' },
			{ name: lang('WYSIWYG_LIST_ORDERED'), value: 'ordered' },
		];
		lists.forEach(l => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = l.name;
			item.setAttribute('role', 'button');
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (l.value === 'bullet') {
					editor.chain().focus().toggleBulletList().run();
				} else if (l.value === 'ordered') {
					editor.chain().focus().toggleOrderedList().run();
				} else {
					if (editor.isActive('bulletList')) {
						editor.chain().focus().toggleBulletList().run();
					} else if (editor.isActive('orderedList')) {
						editor.chain().focus().toggleOrderedList().run();
					}
				}
				closeAllMenus();
			});
			listMenu.appendChild(item);
		});
		listBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = listMenu.style.display === 'block';
			closeAllMenus();
			if (!isVisible) {
				listMenu.style.display = 'block';
				listBtn.setAttribute('aria-expanded', 'true');
			}
		});
		listContainer.appendChild(listBtn);
		listContainer.appendChild(listMenu);

		// 3. Size Dropdown Menu
		const sizeContainer = document.createElement('div');
		sizeContainer.className = 'wysiwyg-dropdown select-size';

		const sizeBtn = document.createElement('button');
		sizeBtn.type = 'button';
		sizeBtn.className = 'wysiwyg-btn btn-size-toggle';
		sizeBtn.title = lang('WYSIWYG_SIZE');
		sizeBtn.setAttribute('aria-label', lang('WYSIWYG_SIZE'));
		sizeBtn.setAttribute('aria-haspopup', 'true');
		sizeBtn.setAttribute('aria-expanded', 'false');
		sizeBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M9 4v3h5v12h3V7h5V4H9zm-6 8h3v7h3v-7h3V9H3v3z"/></svg><span class="wysiwyg-caret"></span>';

		const sizeMenu = document.createElement('div');
		sizeMenu.className = 'wysiwyg-dropdown-menu';

		const sizes = [
			{ name: lang('WYSIWYG_SIZE_NORMAL'), value: '' },
			{ name: lang('WYSIWYG_SIZE_TINY'), value: '50' },
			{ name: lang('WYSIWYG_SIZE_SMALL'), value: '85' },
			{ name: lang('WYSIWYG_SIZE_LARGE'), value: '150' },
			{ name: lang('WYSIWYG_SIZE_HUGE'), value: '200' },
		];
		sizes.forEach(s => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = s.name;
			item.setAttribute('role', 'button');
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (s.value) {
					editor.chain().focus().setFontSize(s.value).run();
				} else {
					editor.chain().focus().unsetFontSize().run();
				}
				closeAllMenus();
			});
			sizeMenu.appendChild(item);
		});
		sizeBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = sizeMenu.style.display === 'block';
			closeAllMenus();
			if (!isVisible) {
				sizeMenu.style.display = 'block';
				sizeBtn.setAttribute('aria-expanded', 'true');
			}
		});
		sizeContainer.appendChild(sizeBtn);
		sizeContainer.appendChild(sizeMenu);

		// 3.5 Table Dropdown Menu
		const tableContainer = document.createElement('div');
		tableContainer.className = 'wysiwyg-dropdown select-table';

		const tableBtn = document.createElement('button');
		tableBtn.type = 'button';
		tableBtn.className = 'wysiwyg-btn btn-table-toggle';
		tableBtn.title = lang('WYSIWYG_TABLE');
		tableBtn.setAttribute('aria-label', lang('WYSIWYG_TABLE'));
		tableBtn.setAttribute('aria-haspopup', 'true');
		tableBtn.setAttribute('aria-expanded', 'false');
		tableBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 20H4v-4h4v4zm0-6H4v-4h4v4zm0-6H4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4z"/></svg><span class="wysiwyg-caret"></span>';

		const tableMenu = document.createElement('div');
		tableMenu.className = 'wysiwyg-dropdown-menu';
		tableMenu.style.minWidth = '185px';

		const tableOptions = [
			{ name: lang('WYSIWYG_TABLE_INSERT'), action: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(), alwaysEnabled: true },
			{ name: lang('WYSIWYG_TABLE_ADD_ROW_BEFORE'), action: () => editor.chain().focus().addRowBefore().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_ROW_AFTER'), action: () => editor.chain().focus().addRowAfter().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_ROW'), action: () => editor.chain().focus().deleteRow().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_COL_BEFORE'), action: () => editor.chain().focus().addColumnBefore().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_COL_AFTER'), action: () => editor.chain().focus().addColumnAfter().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_COL'), action: () => editor.chain().focus().deleteColumn().run() },
			{ name: lang('WYSIWYG_TABLE_MERGE_CELLS'), action: () => editor.chain().focus().mergeCells().run() },
			{ name: lang('WYSIWYG_TABLE_SPLIT_CELL'), action: () => editor.chain().focus().splitCell().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_TABLE'), action: () => editor.chain().focus().deleteTable().run() },
		];

		tableOptions.forEach(opt => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = opt.name;
			item.setAttribute('role', 'button');
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (opt.alwaysEnabled || editor.isActive('table')) {
					opt.action();
				}
				closeAllMenus();
			});
			tableMenu.appendChild(item);
		});

		tableBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = tableMenu.style.display === 'block';
			closeAllMenus();
			if (!isVisible) {
				tableMenu.style.display = 'block';
				tableBtn.setAttribute('aria-expanded', 'true');
			}

			const inTable = editor.isActive('table');
			Array.from(tableMenu.children).forEach((child, index) => {
				const opt = tableOptions[index];
				if (!opt.alwaysEnabled) {
					if (inTable) {
						child.removeAttribute('disabled');
						child.style.opacity = '1';
						child.style.pointerEvents = 'auto';
					} else {
						child.setAttribute('disabled', 'true');
						child.style.opacity = '0.5';
						child.style.pointerEvents = 'none';
					}
				}
			});
		});

		tableContainer.appendChild(tableBtn);
		tableContainer.appendChild(tableMenu);

		// 5. Populate Custom BBCodes 2nd Row
		if (customBbcodesRow && typeof customBbcodes !== 'undefined' && customBbcodes.length > 0) {
			customBbcodes.forEach(bb => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = `wysiwyg-btn btn-custom-bbcode btn-bbcode-${bb.tag}`;
				button.textContent = `[${bb.tag}]`;
				const tooltip = bb.helpline || `[${bb.tag}]`;
				button.title = tooltip;
				button.setAttribute('aria-label', tooltip);
				button.setAttribute('role', 'button');

				button.addEventListener('click', (e) => {
					e.preventDefault();
					e.stopPropagation();
					closeAllMenus();

					let val = '';
					if (bb.has_val) {
						const promptTemplate = lang('WYSIWYG_PROMPT_CUSTOM_BBCODE');
						const promptMsg = promptTemplate.replace('%s', bb.tag);
						const inputVal = window.prompt(promptMsg, '');
						if (inputVal === null) return;
						val = inputVal.trim();
					}

					// Get selected text if any
					const { state } = editor;
					const { from, to, empty } = state.selection;
					let selectedText = '';
					if (!empty) {
						selectedText = state.doc.textBetween(from, to, ' ');
					}

					let renderedHtml;
					if (bb.tpl) {
						let tplHtml = bb.tpl;
						const textReplacement = selectedText || '...';
						const wrappedReplacement = `<span data-bbcode-content="true">${textReplacement}</span>`;
						tplHtml = tplHtml.replace(/\{(TEXT|SIMPLETEXT|INTTEXT|IDENTIFIER|COLOR|NUMBER|URL)\d*\}/gi, (match) => {
							if (val && !match.match(/TEXT/i)) {
								return val;
							}
							return wrappedReplacement;
						});

						const parser = new window.DOMParser();
						const parsedDoc = parser.parseFromString(`<div>${tplHtml}</div>`, 'text/html');
						const rootEl = parsedDoc.body.firstElementChild;
						if (rootEl) {
							const targetEl = (rootEl.children.length === 1) ? rootEl.firstElementChild : rootEl;
							targetEl.setAttribute('data-bbcode', bb.tag);
							targetEl.setAttribute('data-custom-bbcode', 'true');
							if (val) {
								targetEl.setAttribute('data-bbcode-val', val);
							}
							renderedHtml = rootEl.innerHTML;
						} else {
							renderedHtml = tplHtml;
						}
					} else {
						const textReplacement = selectedText || '...';
						renderedHtml = `<span data-bbcode="${bb.tag}" data-custom-bbcode="true"${val ? ` data-bbcode-val="${val}"` : ''}><span data-bbcode-content="true">${textReplacement}</span></span>`;
					}

					editor.chain().focus().insertContent(renderedHtml).run();
				});

				customBbcodesRow.appendChild(button);
			});
		}

		// 6. Color Picker Dropdown Menu
		const colorPickerContainer = document.createElement('div');
		colorPickerContainer.className = 'wysiwyg-color-picker-container wysiwyg-dropdown';

		const colorBtn = document.createElement('button');
		colorBtn.type = 'button';
		colorBtn.className = 'wysiwyg-btn btn-color';
		colorBtn.title = lang('WYSIWYG_FONT_COLOR');
		colorBtn.setAttribute('aria-label', lang('WYSIWYG_FONT_COLOR'));
		colorBtn.setAttribute('aria-haspopup', 'true');
		colorBtn.setAttribute('aria-expanded', 'false');
		colorBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M12 2C6.49 2 2 6.49 2 12c0 4.41 3.59 8 8 8 1.1 0 2-.9 2-2 0-.46-.17-.89-.47-1.22-.3-.33-.48-.77-.48-1.28 0-1.1.9-2 2-2h2.45C18.44 13.5 22 9.94 22 5.5 22 3.57 20.43 2 18.5 2H12zm-5.5 8c-.83 0-1.5-.67-1.5-1.5S5.67 7 6.5 7s1.5.67 1.5 1.5S7.33 10 6.5 10zm3-4C8.67 6 8 5.33 8 4.5S8.67 3 9.5 3s1.5.67 1.5 1.5S10.33 6 9.5 6zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 3 14.5 3s1.5.67 1.5 1.5S15.33 6 14.5 6zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 7 17.5 7s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg><span class="wysiwyg-color-bar"></span>';

		const colorBar = colorBtn.querySelector('.wysiwyg-color-bar');

		const colorPalette = document.createElement('div');
		colorPalette.className = 'wysiwyg-color-palette wysiwyg-dropdown-menu';

		const paletteGrid = document.createElement('div');
		paletteGrid.className = 'wysiwyg-color-grid';

		const paletteColors = [
			'#000000', '#444444', '#666666', '#999999', '#CCCCCC', '#EEEEEE', '#FFFFFF',
			'#FF0000', '#FF9900', '#FFFF00', '#00FF00', '#00FFFF', '#0000FF', '#9900FF', '#FF00FF',
			'#EA9999', '#F9CB9C', '#FFE599', '#B6D7A8', '#A2C4C9', '#9FC5E8', '#B4A7D6', '#D5A6BD',
			'#CC0000', '#E69138', '#F1C232', '#6AA84F', '#45818E', '#3D85C6', '#674EA7', '#A64D79',
			'#660000', '#783F04', '#7F6000', '#274E13', '#0C343D', '#073763', '#20124D', '#4C1130'
		];

		paletteColors.forEach(color => {
			const colorBox = document.createElement('button');
			colorBox.type = 'button';
			colorBox.className = 'wysiwyg-color-box';
			colorBox.style.backgroundColor = color;
			colorBox.title = color;
			colorBox.setAttribute('aria-label', color);
			colorBox.setAttribute('role', 'button');
			colorBox.addEventListener('click', (e) => {
				e.stopPropagation();
				editor.chain().focus().setColor(color).run();
				closeAllMenus();
				updateColorBtnIndicator(color);
			});
			paletteGrid.appendChild(colorBox);
		});

		const clearColorBtn = document.createElement('button');
		clearColorBtn.type = 'button';
		clearColorBtn.className = 'wysiwyg-clear-color-btn';
		clearColorBtn.textContent = lang('WYSIWYG_DEFAULT_COLOR');
		clearColorBtn.setAttribute('role', 'button');
		clearColorBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			editor.chain().focus().unsetColor().run();
			closeAllMenus();
			updateColorBtnIndicator('');
		});

		colorPalette.appendChild(paletteGrid);
		colorPalette.appendChild(clearColorBtn);
		colorPickerContainer.appendChild(colorBtn);
		colorPickerContainer.appendChild(colorPalette);

		function updateColorBtnIndicator(color) {
			if (color) {
				colorBar.style.backgroundColor = color;
			} else {
				colorBar.style.backgroundColor = 'currentColor';
			}
		}

		colorBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = colorPalette.style.display === 'block';
			closeAllMenus();
			if (!isVisible) {
				colorPalette.style.display = 'block';
				colorBtn.setAttribute('aria-expanded', 'true');
			}
		});

		colorPalette.addEventListener('click', (e) => {
			e.stopPropagation();
		});

		// Create toolbar buttons matching exact mockup order
		const buttons = [
			{ name: 'undo', type: 'button', label: lang('WYSIWYG_UNDO'), action: () => editor.chain().focus().undo().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>' },
			{ name: 'redo', type: 'button', label: lang('WYSIWYG_REDO'), action: () => editor.chain().focus().redo().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>' },
			{ type: 'separator' },
			{ name: 'heading', type: 'custom', element: headingContainer },
			{ name: 'lists', type: 'custom', element: listContainer },
			{ name: 'size', type: 'custom', element: sizeContainer },
			{ name: 'blockquote', type: 'button', label: lang('WYSIWYG_QUOTE'), action: () => editor.chain().focus().toggleBlockquote().run(), active: () => editor.isActive('blockquote'), icon: '<svg viewBox="0 0 24 24"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>' },
			{ name: 'codeBlock', type: 'button', label: lang('WYSIWYG_CODE_BLOCK'), action: () => editor.chain().focus().toggleCodeBlock().run(), active: () => editor.isActive('codeBlock'), icon: '<svg viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>' },
			{ type: 'separator' },
			{ name: 'bold', type: 'button', label: lang('WYSIWYG_BOLD'), action: () => editor.chain().focus().toggleBold().run(), active: () => editor.isActive('bold'), icon: '<svg viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c1.1 0 2 .9 2 2s-.9 2-2 2h-3v-4zm3.5 9h-3.5v-4h3.5c1.1 0 2 .9 2 2s-.9 2-2 2z"/></svg>' },
			{ name: 'italic', type: 'button', label: lang('WYSIWYG_ITALIC'), action: () => editor.chain().focus().toggleItalic().run(), active: () => editor.isActive('italic'), icon: '<svg viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>' },
			{ name: 'strike', type: 'button', label: lang('WYSIWYG_S'), action: () => editor.chain().focus().toggleStrike().run(), active: () => editor.isActive('strike'), icon: '<svg viewBox="0 0 24 24"><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg>' },
			{ name: 'underline', type: 'button', label: lang('WYSIWYG_UNDERLINE'), action: () => editor.chain().focus().toggleUnderline().run(), active: () => editor.isActive('underline'), icon: '<svg viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>' },
			{ name: 'color', type: 'custom', element: colorPickerContainer },
			{ name: 'highlight', type: 'button', label: lang('WYSIWYG_HIGHLIGHT'), action: () => editor.chain().focus().toggleHighlight().run(), active: () => editor.isActive('highlight'), icon: '<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; vertical-align: middle;"><path d="M15.24 8.07l2.69 2.69L9.76 18.93l-2.69-2.69L15.24 8.07zm4.77-1.5l-3.18-3.18c-.39-.39-1.02-.39-1.41 0l-1.49 1.49 4.59 4.59 1.49-1.49c.39-.39.39-1.02 0-1.41zM2 22h5.5l.5-.5-6-6-.5.5V22z"/></svg>' },
			{ name: 'link', type: 'button', label: lang('WYSIWYG_LINK'), action: () => {
				const prevUrl = editor.getAttributes('link').href;
				const url = window.prompt(lang('WYSIWYG_URL_PROMPT'), prevUrl);
				if (url === null) return;
				if (url === '') {
					editor.chain().focus().extendMarkRange('link').unsetLink().run();
				} else {
					editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
				}
			}, active: () => editor.isActive('link'), icon: '<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>' },
			{ name: 'hr', type: 'button', label: lang('WYSIWYG_HR'), action: () => editor.chain().focus().setHorizontalRule().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M4 11h16v2H4z"/></svg>' },
			{ name: 'table', type: 'custom', element: tableContainer },
			{ type: 'separator' },
			{ name: 'superscript', type: 'button', label: lang('WYSIWYG_SUPERSCRIPT'), action: () => editor.chain().focus().toggleSuperscript().run(), active: () => editor.isActive('superscript'), icon: '<svg viewBox="0 0 24 24"><path d="M19.62 9.07c-.02-.13-.06-.25-.13-.36-.08-.12-.17-.22-.29-.29-.12-.07-.25-.12-.39-.14-.14-.02-.29-.02-.43 0H14v1h3.19l-3.69 3.69c-.19.19-.29.44-.29.71s.1.52.29.71.44.29.71.29.52-.1.71-.29l3.69-3.69V15h1v-4.38c0-.14-.01-.29-.04-.43-.02-.14-.07-.27-.14-.39-.08-.12-.18-.21-.29-.28-.11-.08-.24-.13-.37-.15zm-7.91-.71L8.5 11.57l-3.21-3.21-1.41 1.41 3.21 3.21-3.21 3.21 1.41 1.41 3.21-3.21 3.21 3.21 1.41-1.41-3.21-3.21 3.21-3.21-1.41-1.41z"/></svg>' },
			{ name: 'subscript', type: 'button', label: lang('WYSIWYG_SUBSCRIPT'), action: () => editor.chain().focus().toggleSubscript().run(), active: () => editor.isActive('subscript'), icon: '<svg viewBox="0 0 24 24"><path d="M19.62 15.07c-.02-.13-.06-.25-.13-.36-.08-.12-.17-.22-.29-.29-.12-.07-.25-.12-.39-.14-.14-.02-.29-.02-.43 0H14v1h3.19l-3.69 3.69c-.19.19-.29.44-.29.71s.1.52.29.71.44.29.71.29.52-.1.71-.29l3.69-3.69V21h1v-4.38c0-.14-.01-.29-.04-.43-.02-.14-.07-.27-.14-.39-.08-.12-.18-.21-.29-.28-.11-.08-.24-.13-.37-.15zm-7.91-.71L8.5 17.57l-3.21-3.21-1.41 1.41 3.21 3.21-3.21 3.21 1.41 1.41 3.21-3.21 3.21 3.21 1.41-1.41-3.21-3.21 3.21-3.21-1.41-1.41z"/></svg>' },
			{ type: 'separator' },
			{ name: 'alignLeft', type: 'button', label: lang('WYSIWYG_ALIGN_LEFT'), action: () => editor.chain().focus().setTextAlign('left').run(), active: () => editor.isActive({ textAlign: 'left' }), icon: '<svg viewBox="0 0 24 24"><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignCenter', type: 'button', label: lang('WYSIWYG_ALIGN_CENTER'), action: () => editor.chain().focus().setTextAlign('center').run(), active: () => editor.isActive({ textAlign: 'center' }), icon: '<svg viewBox="0 0 24 24"><path d="M7 15h10v2H7v-2zm0-8h10v2H7V7zm-4 6h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignRight', type: 'button', label: lang('WYSIWYG_ALIGN_RIGHT'), action: () => editor.chain().focus().setTextAlign('right').run(), active: () => editor.isActive({ textAlign: 'right' }), icon: '<svg viewBox="0 0 24 24"><path d="M9 15h12v2H9v-2zm0-8h12v2H9V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignJustify', type: 'button', label: lang('WYSIWYG_ALIGN_JUSTIFY'), action: () => editor.chain().focus().setTextAlign('justify').run(), active: () => editor.isActive({ textAlign: 'justify' }), icon: '<svg viewBox="0 0 24 24"><path d="M3 15h18v2H3v-2zm0-8h18v2H3V7zm0 6h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			...window.phpbbWysiwyg.buttons,
		];

		// Toggle source button
		if (allowToggleGlobal) {
			buttons.push({ type: 'separator' });
			buttons.push({
				name: 'toggleSource',
				label: lang('WYSIWYG_TOGGLE_SOURCE'),
				action: () => toggleSourceMode(),
				active: () => false,
				icon: '<svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm-5 14H4v-4h11v4zm0-5H4V9h11v4zm5 5h-4V9h4v9z"/></svg>'
			});
		}

		const buttonElements = {};
		buttons.forEach(btn => {
			if (btn.type === 'separator') {
				const sep = document.createElement('div');
				sep.className = 'wysiwyg-separator';
				sep.setAttribute('role', 'separator');
				toolbarEl.appendChild(sep);
			} else if (btn.type === 'custom') {
				toolbarEl.appendChild(btn.element);
			} else {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = `wysiwyg-btn btn-${btn.name}`;
				button.title = btn.label;
				button.setAttribute('aria-label', btn.label);
				button.setAttribute('role', 'button');
				if (btn.active) {
					button.setAttribute('aria-pressed', btn.active() ? 'true' : 'false');
				}
				button.innerHTML = btn.icon;
				button.addEventListener('click', btn.action);
				toolbarEl.appendChild(button);
				buttonElements[btn.name] = button;
			}
		});

		function updateToolbarActiveStates() {
			Object.keys(buttonElements).forEach(name => {
				const btn = buttons.find(b => b.name === name);
				if (btn && btn.active) {
					const isActive = btn.active();
					if (isActive) {
						buttonElements[name].classList.add('is-active');
						buttonElements[name].setAttribute('aria-pressed', 'true');
					} else {
						buttonElements[name].classList.remove('is-active');
						buttonElements[name].setAttribute('aria-pressed', 'false');
					}
				}
			});

			// Update sizeBtn active class
			const sizeAttrs = editor.getAttributes('fontSize');
			if (sizeAttrs && sizeAttrs.fontSize) {
				sizeBtn.classList.add('is-active');
			} else {
				sizeBtn.classList.remove('is-active');
			}

			// Update headingBtn active class
			if (editor.isActive('heading')) {
				headingBtn.classList.add('is-active');
			} else {
				headingBtn.classList.remove('is-active');
			}

			// Update listBtn active class
			if (editor.isActive('bulletList') || editor.isActive('orderedList')) {
				listBtn.classList.add('is-active');
			} else {
				listBtn.classList.remove('is-active');
			}

			// Update color colorBtn indicator based on active textStyle color
			const textStyleAttrs = editor.getAttributes('textStyle');
			if (textStyleAttrs && textStyleAttrs.color) {
				updateColorBtnIndicator(textStyleAttrs.color);
			} else {
				updateColorBtnIndicator('');
			}
		}

		let isSourceMode = false;
		function toggleSourceMode() {
			isSourceMode = !isSourceMode;

			if (isSourceMode) {
				const html = editor.getHTML();
				contentEl.style.opacity = '0.5';

				const formData = new FormData();
				formData.append('action', 'html_to_bbcode');
				formData.append('html', html);

				fetch(htmlToBbcodeUrl || form.action || window.location.href, {
					method: 'POST',
					body: formData
				})
				.then(res => res.json())
				.then(data => {
					contentEl.style.opacity = '1';
					if (data.bbcode !== undefined) {
						textarea.value = data.bbcode;
						contentEl.style.display = 'none';
						textarea.style.display = 'block';
						wysiwygUsed.value = '0';
						document.body.classList.remove('wysiwyg-active');
					}
				})
				.catch(() => {
					contentEl.style.opacity = '1';
					isSourceMode = false;
				});
			} else {
				const bbcode = textarea.value;
				contentEl.style.opacity = '0.5';

				const formData = new FormData();
				formData.append('action', 'bbcode_to_html');
				formData.append('bbcode', bbcode);

				fetch(bbcodeToHtmlUrl || form.action || window.location.href, {
					method: 'POST',
					body: formData
				})
				.then(res => res.json())
				.then(data => {
					contentEl.style.opacity = '1';
					if (data.html !== undefined) {
						editor.commands.setContent(data.html);
						textarea.style.display = 'none';
						contentEl.style.display = 'block';
						wysiwygUsed.value = '1';
						document.body.classList.add('wysiwyg-active');
					}
				})
				.catch(() => {
					contentEl.style.opacity = '1';
					isSourceMode = true;
				});
			}
		}

		// Intercept insert_text function of phpBB to handle native clicks
		let originalInsertText = window.insert_text;
		if (originalInsertText) {
			hookInsertText();
		} else {
			let tempInsertText = undefined;
			Object.defineProperty(window, 'insert_text', {
				get() {
					return tempInsertText;
				},
				set(val) {
					originalInsertText = val;
					tempInsertText = function (text, spaces, popup) {
						if (isSourceMode) {
							return originalInsertText(text, spaces, popup);
						}
						const textToInsert = spaces ? ' ' + text + ' ' : text;
						const formData = new FormData();
						formData.append('action', 'bbcode_to_html');
						formData.append('bbcode', textToInsert.trim());

						fetch(bbcodeToHtmlUrl || form.action || window.location.href, {
							method: 'POST',
							body: formData
						})
						.then(res => res.json())
						.then(data => {
							if (data.html !== undefined) {
								editor.chain().focus().insertContent(data.html).run();
							}
						})
						.catch(() => {
							editor.chain().focus().insertContent(textToInsert).run();
						});
					};
				},
				configurable: true
			});
		}

		function hookInsertText() {
			window.insert_text = function (text, spaces, popup) {
				if (isSourceMode) {
					return originalInsertText(text, spaces, popup);
				}
				const textToInsert = spaces ? ' ' + text + ' ' : text;
				const formData = new FormData();
				formData.append('action', 'bbcode_to_html');
				formData.append('bbcode', textToInsert.trim());

				fetch(bbcodeToHtmlUrl || form.action || window.location.href, {
					method: 'POST',
					body: formData
				})
				.then(res => res.json())
				.then(data => {
					if (data.html !== undefined) {
						editor.chain().focus().insertContent(data.html).run();
					}
				})
				.catch(() => {
					editor.chain().focus().insertContent(textToInsert).run();
				});
			};
		}

		// Register instance in global registry
		if (window.phpbbWysiwyg && window.phpbbWysiwyg.instances) {
			window.phpbbWysiwyg.instances.set(textarea, editor);
		}

		// Dispatch integration event
		window.dispatchEvent(new CustomEvent('phpbbWysiwygInit', {
			detail: {
				editor: editor,
				textarea: textarea,
				wysiwygUsed: wysiwygUsed
			}
		}));
	}
});
