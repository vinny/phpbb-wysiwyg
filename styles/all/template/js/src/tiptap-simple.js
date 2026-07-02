import { Editor, Mark, Node } from '@tiptap/core';
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
	registerExtension(ext) {
		this.extensions.push(ext);
	},
	registerButton(btn) {
		this.buttons.push(btn);
	}
};

document.addEventListener('DOMContentLoaded', () => {
	const textareas = [];

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

	// Helper to translate key with fallback to English representation
	function lang(key, fallback = '') {
		return translations[key] || fallback;
	}

	// 1. Frontend message or signature textareas
	const mainTextarea = document.getElementById('message') || document.getElementById('signature');
	if (mainTextarea && container && !mainTextarea.disabled && mainTextarea.style.display !== 'none') {
		const initialContentTextarea = document.getElementById('wysiwyg-initial-content');
		const initialHtml = initialContentTextarea ? initialContentTextarea.value : (container.getAttribute('data-initial-content') || '');
		textareas.push({
			element: mainTextarea,
			initialHtml: initialHtml,
			type: 'frontend'
		});
	}

	// 2. ACP textareas (forum rules, descriptions, etc.)
	const acpTextareas = document.querySelectorAll('textarea[data-bbcode="true"]');
	acpTextareas.forEach(ta => {
		if (ta && !ta.disabled && ta.style.display !== 'none') {
			textareas.push({
				element: ta,
				initialHtml: null, // Load via AJAX
				type: 'acp'
			});
		}
	});

	if (textareas.length === 0) return;

	// Add body layout flag
	document.body.classList.add('wysiwyg-active');

	// Initialize each textarea
	textareas.forEach(target => {
		initWysiwyg(target);
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
		wrapper.className = 'wysiwyg-editor-wrapper';

		const toolbarEl = document.createElement('div');
		toolbarEl.className = 'wysiwyg-toolbar';

		const contentEl = document.createElement('div');
		contentEl.className = 'wysiwyg-content-area';

		const footerEl = document.createElement('div');
		footerEl.className = 'wysiwyg-footer';
		const charCountEl = document.createElement('span');
		charCountEl.className = 'wysiwyg-char-count';
		charCountEl.textContent = lang('WYSIWYG_CHARACTERS', 'Characters: %d').replace('%d', '0');
		footerEl.appendChild(charCountEl);

		wrapper.appendChild(toolbarEl);
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
				return ['span', HTMLAttributes, 0];
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
					`📎 ${HTMLAttributes['data-filename'] || 'Attachment'}`
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
					}
				};
			},
			parseHTML() {
				return [
					{
						tag: 'blockquote',
						getAttrs: dom => {
							let author = dom.getAttribute('data-author') || dom.getAttribute('author');
							const cite = dom.querySelector('cite');
							if (cite) {
								if (!author) {
									const text = cite.textContent.trim();
									const lastColon = text.lastIndexOf(':');
									const cleanText = lastColon !== -1 ? text.substring(0, lastColon) : text;
									const wroteWord = lang('WYSIWYG_WROTE', 'wrote');
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
							return { author: author || null };
						}
					}
				];
			},
			renderHTML({ node, HTMLAttributes }) {
				const author = node.attrs.author;
				if (author) {
					const wrote = lang('WYSIWYG_WROTE', 'wrote');
					return [
						'blockquote',
						{ ...HTMLAttributes, 'data-author': author },
						[
							'div',
							{},
							['cite', {}, `${author} ${wrote}:`],
							0
						]
					];
				}
				return [
					'blockquote',
					{ ...HTMLAttributes, class: 'uncited' },
					[
						'div',
						{},
						0
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
				const selectAllText = lang('WYSIWYG_SELECT_ALL_CODE', 'Select all');
				const codeText = lang('WYSIWYG_CODE_LABEL', 'Code');
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
					['summary', {}, 'Spoiler'],
					0
				];
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
			onUpdate: ({ editor }) => {
				if (wysiwygUsed.value === '1') {
					textarea.value = editor.getHTML();
				}
				// Update character count
				const count = editor.storage.characterCount.characters();
				charCountEl.textContent = lang('WYSIWYG_CHARACTERS', 'Characters: %d').replace('%d', count);
			},
			onSelectionUpdate: () => {
				updateToolbarActiveStates();
			},
		});

		// Initial sync
		textarea.value = editor.getHTML();
		const initialCount = editor.storage.characterCount.characters();
		charCountEl.textContent = lang('WYSIWYG_CHARACTERS', 'Characters: %d').replace('%d', initialCount);

		// Helper to close all dropdown menus
		function closeAllMenus() {
			headingMenu.style.display = 'none';
			listMenu.style.display = 'none';
			sizeMenu.style.display = 'none';
			colorPalette.style.display = 'none';
			tableMenu.style.display = 'none';
		}

		// Close menus when clicking outside
		document.addEventListener('click', () => {
			closeAllMenus();
		});

		// 1. Heading Dropdown Menu
		const headingContainer = document.createElement('div');
		headingContainer.className = 'wysiwyg-dropdown select-heading';

		const headingBtn = document.createElement('button');
		headingBtn.type = 'button';
		headingBtn.className = 'wysiwyg-btn btn-heading-toggle';
		headingBtn.title = lang('WYSIWYG_HEADING', 'Heading');
		headingBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M19 19h-2v-6h-6v6H9V5h2v6h6V5h2v14z"/></svg><span class="wysiwyg-caret"></span>';

		const headingMenu = document.createElement('div');
		headingMenu.className = 'wysiwyg-dropdown-menu';

		const headings = [
			{ name: lang('WYSIWYG_HEADING_P', 'Paragraph'), value: 'paragraph' },
			{ name: lang('WYSIWYG_HEADING_1', 'Heading 1'), value: '1' },
			{ name: lang('WYSIWYG_HEADING_2', 'Heading 2'), value: '2' },
			{ name: lang('WYSIWYG_HEADING_3', 'Heading 3'), value: '3' },
			{ name: lang('WYSIWYG_HEADING_4', 'Heading 4'), value: '4' },
		];
		headings.forEach(h => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = h.name;
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (h.value === 'paragraph') {
					editor.chain().focus().setParagraph().run();
				} else {
					editor.chain().focus().toggleHeading({ level: parseInt(h.value) }).run();
				}
				headingMenu.style.display = 'none';
			});
			headingMenu.appendChild(item);
		});
		headingBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = headingMenu.style.display === 'block';
			closeAllMenus();
			headingMenu.style.display = isVisible ? 'none' : 'block';
		});
		headingContainer.appendChild(headingBtn);
		headingContainer.appendChild(headingMenu);

		// 2. List Dropdown Menu
		const listContainer = document.createElement('div');
		listContainer.className = 'wysiwyg-dropdown select-lists';

		const listBtn = document.createElement('button');
		listBtn.type = 'button';
		listBtn.className = 'wysiwyg-btn btn-list-toggle';
		listBtn.title = lang('WYSIWYG_LISTS', 'Lists');
		listBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M4 10.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0-6c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zm0 12c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM7 19h14v-2H7v2zm0-6h14v-2H7v2zm0-8v2h14V5H7z"/></svg><span class="wysiwyg-caret"></span>';

		const listMenu = document.createElement('div');
		listMenu.className = 'wysiwyg-dropdown-menu';

		const lists = [
			{ name: lang('WYSIWYG_LIST_NONE', 'No List'), value: 'none' },
			{ name: lang('WYSIWYG_LIST_BULLET', 'Bullet List'), value: 'bullet' },
			{ name: lang('WYSIWYG_LIST_ORDERED', 'Ordered List'), value: 'ordered' },
		];
		lists.forEach(l => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = l.name;
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
				listMenu.style.display = 'none';
			});
			listMenu.appendChild(item);
		});
		listBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = listMenu.style.display === 'block';
			closeAllMenus();
			listMenu.style.display = isVisible ? 'none' : 'block';
		});
		listContainer.appendChild(listBtn);
		listContainer.appendChild(listMenu);

		// 3. Size Dropdown Menu
		const sizeContainer = document.createElement('div');
		sizeContainer.className = 'wysiwyg-dropdown select-size';

		const sizeBtn = document.createElement('button');
		sizeBtn.type = 'button';
		sizeBtn.className = 'wysiwyg-btn btn-size-toggle';
		sizeBtn.title = lang('WYSIWYG_SIZE', 'Size');
		sizeBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M9 4v3h5v12h3V7h5V4H9zm-6 8h3v7h3v-7h3V9H3v3z"/></svg><span class="wysiwyg-caret"></span>';

		const sizeMenu = document.createElement('div');
		sizeMenu.className = 'wysiwyg-dropdown-menu';

		const sizes = [
			{ name: lang('WYSIWYG_SIZE_NORMAL', 'Normal Size'), value: '' },
			{ name: lang('WYSIWYG_SIZE_TINY', 'Tiny'), value: '50' },
			{ name: lang('WYSIWYG_SIZE_SMALL', 'Small'), value: '85' },
			{ name: lang('WYSIWYG_SIZE_LARGE', 'Large'), value: '150' },
			{ name: lang('WYSIWYG_SIZE_HUGE', 'Huge'), value: '200' },
		];
		sizes.forEach(s => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = s.name;
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (s.value) {
					editor.chain().focus().setFontSize(s.value).run();
				} else {
					editor.chain().focus().unsetFontSize().run();
				}
				sizeMenu.style.display = 'none';
			});
			sizeMenu.appendChild(item);
		});
		sizeBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = sizeMenu.style.display === 'block';
			closeAllMenus();
			sizeMenu.style.display = isVisible ? 'none' : 'block';
		});
		sizeContainer.appendChild(sizeBtn);
		sizeContainer.appendChild(sizeMenu);

		// 3.5 Table Dropdown Menu
		const tableContainer = document.createElement('div');
		tableContainer.className = 'wysiwyg-dropdown select-table';

		const tableBtn = document.createElement('button');
		tableBtn.type = 'button';
		tableBtn.className = 'wysiwyg-btn btn-table-toggle';
		tableBtn.title = lang('WYSIWYG_TABLE', 'Table');
		tableBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zM8 20H4v-4h4v4zm0-6H4v-4h4v4zm0-6H4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4zm6 12h-4v-4h4v4zm0-6h-4v-4h4v4zm0-6h-4V4h4v4z"/></svg><span class="wysiwyg-caret"></span>';

		const tableMenu = document.createElement('div');
		tableMenu.className = 'wysiwyg-dropdown-menu';
		tableMenu.style.minWidth = '185px';

		const tableOptions = [
			{ name: lang('WYSIWYG_TABLE_INSERT', 'Insert Table (3x3)'), action: () => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run(), alwaysEnabled: true },
			{ name: lang('WYSIWYG_TABLE_ADD_ROW_BEFORE', 'Add Row Above'), action: () => editor.chain().focus().addRowBefore().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_ROW_AFTER', 'Add Row Below'), action: () => editor.chain().focus().addRowAfter().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_ROW', 'Delete Row'), action: () => editor.chain().focus().deleteRow().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_COL_BEFORE', 'Add Column Before'), action: () => editor.chain().focus().addColumnBefore().run() },
			{ name: lang('WYSIWYG_TABLE_ADD_COL_AFTER', 'Add Column After'), action: () => editor.chain().focus().addColumnAfter().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_COL', 'Delete Column'), action: () => editor.chain().focus().deleteColumn().run() },
			{ name: lang('WYSIWYG_TABLE_MERGE_CELLS', 'Merge Cells'), action: () => editor.chain().focus().mergeCells().run() },
			{ name: lang('WYSIWYG_TABLE_SPLIT_CELL', 'Split Cell'), action: () => editor.chain().focus().splitCell().run() },
			{ name: lang('WYSIWYG_TABLE_DELETE_TABLE', 'Delete Table'), action: () => editor.chain().focus().deleteTable().run() },
		];

		tableOptions.forEach(opt => {
			const item = document.createElement('button');
			item.type = 'button';
			item.className = 'wysiwyg-dropdown-item';
			item.textContent = opt.name;
			item.addEventListener('click', (e) => {
				e.stopPropagation();
				if (opt.alwaysEnabled || editor.isActive('table')) {
					opt.action();
				}
				tableMenu.style.display = 'none';
			});
			tableMenu.appendChild(item);
		});

		tableBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = tableMenu.style.display === 'block';
			closeAllMenus();
			tableMenu.style.display = isVisible ? 'none' : 'block';

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

		// 4. Color Picker Dropdown Menu
		const colorPickerContainer = document.createElement('div');
		colorPickerContainer.className = 'wysiwyg-color-picker-container wysiwyg-dropdown';

		const colorBtn = document.createElement('button');
		colorBtn.type = 'button';
		colorBtn.className = 'wysiwyg-btn btn-color';
		colorBtn.title = lang('WYSIWYG_FONT_COLOR', 'Font Color');
		colorBtn.innerHTML = '<svg viewBox="0 0 24 24"><path d="M11 3L5.5 17h2.25l1.12-3h6.25l1.12 3h2.25L13 3h-2zm-1.38 9L12 5.67 14.38 12H9.62z"/></svg>';

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
			const colorBox = document.createElement('div');
			colorBox.className = 'wysiwyg-color-box';
			colorBox.style.backgroundColor = color;
			colorBox.title = color;
			colorBox.addEventListener('click', (e) => {
				e.stopPropagation();
				editor.chain().focus().setColor(color).run();
				colorPalette.style.display = 'none';
				updateColorBtnIndicator(color);
			});
			paletteGrid.appendChild(colorBox);
		});

		const clearColorBtn = document.createElement('button');
		clearColorBtn.type = 'button';
		clearColorBtn.className = 'wysiwyg-clear-color-btn';
		clearColorBtn.textContent = lang('WYSIWYG_DEFAULT_COLOR', 'Default Color');
		clearColorBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			editor.chain().focus().unsetColor().run();
			colorPalette.style.display = 'none';
			updateColorBtnIndicator('');
		});

		colorPalette.appendChild(paletteGrid);
		colorPalette.appendChild(clearColorBtn);
		colorPickerContainer.appendChild(colorBtn);
		colorPickerContainer.appendChild(colorPalette);

		function updateColorBtnIndicator(color) {
			if (color) {
				colorBtn.style.borderBottom = `3px solid ${color}`;
			} else {
				colorBtn.style.borderBottom = 'none';
			}
		}

		colorBtn.addEventListener('click', (e) => {
			e.stopPropagation();
			const isVisible = colorPalette.style.display === 'block';
			closeAllMenus();
			colorPalette.style.display = isVisible ? 'none' : 'block';
		});

		colorPalette.addEventListener('click', (e) => {
			e.stopPropagation();
		});

		// Create toolbar buttons matching exact mockup order
		const buttons = [
			{ name: 'undo', type: 'button', label: lang('WYSIWYG_UNDO', 'Undo'), action: () => editor.chain().focus().undo().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z"/></svg>' },
			{ name: 'redo', type: 'button', label: lang('WYSIWYG_REDO', 'Redo'), action: () => editor.chain().focus().redo().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z"/></svg>' },
			{ type: 'separator' },
			{ name: 'heading', type: 'custom', element: headingContainer },
			{ name: 'lists', type: 'custom', element: listContainer },
			{ name: 'size', type: 'custom', element: sizeContainer },
			{ name: 'blockquote', type: 'button', label: lang('WYSIWYG_QUOTE', 'Quote'), action: () => editor.chain().focus().toggleBlockquote().run(), active: () => editor.isActive('blockquote'), icon: '<svg viewBox="0 0 24 24"><path d="M6 17h3l2-4V7H5v6h3zm8 0h3l2-4V7h-6v6h3z"/></svg>' },
			{ name: 'codeBlock', type: 'button', label: lang('WYSIWYG_CODE_BLOCK', 'Code'), action: () => editor.chain().focus().toggleCodeBlock().run(), active: () => editor.isActive('codeBlock'), icon: '<svg viewBox="0 0 24 24"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>' },
			{ type: 'separator' },
			{ name: 'bold', type: 'button', label: lang('WYSIWYG_BOLD', 'Bold'), action: () => editor.chain().focus().toggleBold().run(), active: () => editor.isActive('bold'), icon: '<svg viewBox="0 0 24 24"><path d="M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c1.1 0 2 .9 2 2s-.9 2-2 2h-3v-4zm3.5 9h-3.5v-4h3.5c1.1 0 2 .9 2 2s-.9 2-2 2z"/></svg>' },
			{ name: 'italic', type: 'button', label: lang('WYSIWYG_ITALIC', 'Italic'), action: () => editor.chain().focus().toggleItalic().run(), active: () => editor.isActive('italic'), icon: '<svg viewBox="0 0 24 24"><path d="M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4z"/></svg>' },
			{ name: 'strike', type: 'button', label: lang('WYSIWYG_S', 'Strike'), action: () => editor.chain().focus().toggleStrike().run(), active: () => editor.isActive('strike'), icon: '<svg viewBox="0 0 24 24"><path d="M10 19h4v-3h-4v3zM5 4v3h5v3h4V7h5V4H5zM3 14h18v-2H3v2z"/></svg>' },
			{ name: 'underline', type: 'button', label: lang('WYSIWYG_UNDERLINE', 'Underline'), action: () => editor.chain().focus().toggleUnderline().run(), active: () => editor.isActive('underline'), icon: '<svg viewBox="0 0 24 24"><path d="M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z"/></svg>' },
			{ name: 'color', type: 'custom', element: colorPickerContainer },
			{ name: 'highlight', type: 'button', label: lang('WYSIWYG_HIGHLIGHT', 'Highlight'), action: () => editor.chain().focus().toggleHighlight().run(), active: () => editor.isActive('highlight'), icon: '<svg viewBox="0 0 24 24" style="width: 16px; height: 16px; vertical-align: middle;"><path d="M15.24 8.07l2.69 2.69L9.76 18.93l-2.69-2.69L15.24 8.07zm4.77-1.5l-3.18-3.18c-.39-.39-1.02-.39-1.41 0l-1.49 1.49 4.59 4.59 1.49-1.49c.39-.39.39-1.02 0-1.41zM2 22h5.5l.5-.5-6-6-.5.5V22z"/></svg>' },
			{ name: 'link', type: 'button', label: lang('WYSIWYG_LINK', 'Link'), action: () => {
				const prevUrl = editor.getAttributes('link').href;
				const url = window.prompt(lang('WYSIWYG_URL_PROMPT', 'URL:'), prevUrl);
				if (url === null) return;
				if (url === '') {
					editor.chain().focus().extendMarkRange('link').unsetLink().run();
				} else {
					editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
				}
			}, active: () => editor.isActive('link'), icon: '<svg viewBox="0 0 24 24"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>' },
			{ name: 'hr', type: 'button', label: lang('WYSIWYG_HR', 'Horizontal Rule'), action: () => editor.chain().focus().setHorizontalRule().run(), active: () => false, icon: '<svg viewBox="0 0 24 24"><path d="M4 11h16v2H4z"/></svg>' },
			{ name: 'table', type: 'custom', element: tableContainer },
			{ type: 'separator' },
			{ name: 'superscript', type: 'button', label: lang('WYSIWYG_SUPERSCRIPT', 'Superscript'), action: () => editor.chain().focus().toggleSuperscript().run(), active: () => editor.isActive('superscript'), icon: '<svg viewBox="0 0 24 24"><path d="M19.62 9.07c-.02-.13-.06-.25-.13-.36-.08-.12-.17-.22-.29-.29-.12-.07-.25-.12-.39-.14-.14-.02-.29-.02-.43 0H14v1h3.19l-3.69 3.69c-.19.19-.29.44-.29.71s.1.52.29.71.44.29.71.29.52-.1.71-.29l3.69-3.69V15h1v-4.38c0-.14-.01-.29-.04-.43-.02-.14-.07-.27-.14-.39-.08-.12-.18-.21-.29-.28-.11-.08-.24-.13-.37-.15zm-7.91-.71L8.5 11.57l-3.21-3.21-1.41 1.41 3.21 3.21-3.21 3.21 1.41 1.41 3.21-3.21 3.21 3.21 1.41-1.41-3.21-3.21 3.21-3.21-1.41-1.41z"/></svg>' },
			{ name: 'subscript', type: 'button', label: lang('WYSIWYG_SUBSCRIPT', 'Subscript'), action: () => editor.chain().focus().toggleSubscript().run(), active: () => editor.isActive('subscript'), icon: '<svg viewBox="0 0 24 24"><path d="M19.62 15.07c-.02-.13-.06-.25-.13-.36-.08-.12-.17-.22-.29-.29-.12-.07-.25-.12-.39-.14-.14-.02-.29-.02-.43 0H14v1h3.19l-3.69 3.69c-.19.19-.29.44-.29.71s.1.52.29.71.44.29.71.29.52-.1.71-.29l3.69-3.69V21h1v-4.38c0-.14-.01-.29-.04-.43-.02-.14-.07-.27-.14-.39-.08-.12-.18-.21-.29-.28-.11-.08-.24-.13-.37-.15zm-7.91-.71L8.5 17.57l-3.21-3.21-1.41 1.41 3.21 3.21-3.21 3.21 1.41 1.41 3.21-3.21 3.21 3.21 1.41-1.41-3.21-3.21 3.21-3.21-1.41-1.41z"/></svg>' },
			{ type: 'separator' },
			{ name: 'alignLeft', type: 'button', label: lang('WYSIWYG_ALIGN_LEFT', 'Align Left'), action: () => editor.chain().focus().setTextAlign('left').run(), active: () => editor.isActive({ textAlign: 'left' }), icon: '<svg viewBox="0 0 24 24"><path d="M15 15H3v2h12v-2zm0-8H3v2h12V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignCenter', type: 'button', label: lang('WYSIWYG_ALIGN_CENTER', 'Align Center'), action: () => editor.chain().focus().setTextAlign('center').run(), active: () => editor.isActive({ textAlign: 'center' }), icon: '<svg viewBox="0 0 24 24"><path d="M7 15h10v2H7v-2zm0-8h10v2H7V7zm-4 6h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignRight', type: 'button', label: lang('WYSIWYG_ALIGN_RIGHT', 'Align Right'), action: () => editor.chain().focus().setTextAlign('right').run(), active: () => editor.isActive({ textAlign: 'right' }), icon: '<svg viewBox="0 0 24 24"><path d="M9 15h12v2H9v-2zm0-8h12v2H9V7zM3 13h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			{ name: 'alignJustify', type: 'button', label: lang('WYSIWYG_ALIGN_JUSTIFY', 'Align Justify'), action: () => editor.chain().focus().setTextAlign('justify').run(), active: () => editor.isActive({ textAlign: 'justify' }), icon: '<svg viewBox="0 0 24 24"><path d="M3 15h18v2H3v-2zm0-8h18v2H3V7zm0 6h18v-2H3v2zm0 8h18v-2H3v2zM3 3v2h18V3H3z"/></svg>' },
			...window.phpbbWysiwyg.buttons,
		];

		// Toggle source button
		const allowToggle = container.id === 'wysiwyg-editor-container' ? (container.getAttribute('data-toggle-enabled') === '1') : true;
		if (allowToggle) {
			buttons.push({ type: 'separator' });
			buttons.push({
				name: 'toggleSource',
				label: lang('WYSIWYG_TOGGLE_SOURCE', 'Toggle Source'),
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
				toolbarEl.appendChild(sep);
			} else if (btn.type === 'custom') {
				toolbarEl.appendChild(btn.element);
			} else {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = `wysiwyg-btn btn-${btn.name}`;
				button.title = btn.label;
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
					if (btn.active()) {
						buttonElements[name].classList.add('is-active');
					} else {
						buttonElements[name].classList.remove('is-active');
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
