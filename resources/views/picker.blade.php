<style>
[x-cloak]{display:none!important}
/* 分段 Tab（与 Filament segmented/toggle 风格保持一致） */
.fi-picker-seg{display:inline-flex;background:#f3f4f6;border-radius:10px;padding:4px;gap:2px;}
.fi-picker-seg-btn{border:none;background:transparent;border-radius:7px;padding:6px 14px;font-size:13px;font-weight:500;color:#6b7280;cursor:pointer;transition:all .15s;white-space:nowrap;}
.fi-picker-seg-btn:hover{color:#374151;}
.fi-picker-seg-btn.active{background:#fff;color:#111827;box-shadow:0 1px 2px rgba(0,0,0,.08);}
/* 分页按钮（中性描边 + primary 高亮，与 Filament 分页一致） */
.fi-picker-page{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;color:#4b5563;font-size:13px;cursor:pointer;transition:all .15s;}
.fi-picker-page:hover:not(:disabled){background:#f9fafb;border-color:#d1d5db;color:#374151;}
.fi-picker-page.active{background:var(--primary-600);border-color:var(--primary-600);color:#fff;font-weight:600;}
.fi-picker-page:disabled{opacity:.45;cursor:not-allowed;}
</style>
<div
    x-data="{
        state: $wire.$entangle('{{ $getStatePath() }}'),
        open: false,
        uploading: false,
        csrf: @js(csrf_token()),
        uploadUrl: @js($getUploadUrl()),
        allowedTypes: @js($getAllowedTypes()),
        directory: @js($getDirectory()),
        multiple: @js($isMultipleMode()),
        previewHeight: @js($getImagePreviewHeight()),
        mergeTypes: @js($isMergeTypes()),
        defaultFilterMode: @js($getDefaultFilterMode()),
        titleText: @js($getPickerTitle()),
        search: '',
        filterMode: @js($getDefaultFilterMode()),
        page: 1,
        perPage: 12,
        items: @js($getPickerMediaForJs()),
        init() {
            if (this.multiple && ! Array.isArray(this.state)) { this.state = []; }
            this._esc = (e) => { if (e.key === 'Escape') this.open = false; };
            this.$watch('open', (v) => {
                if (v) { document.addEventListener('keydown', this._esc); }
                else document.removeEventListener('keydown', this._esc);
            });
            this.$watch('filterMode', () => { this.page = 1; });
            this.$watch('search', () => { this.page = 1; });
        },
        get baseList() {
            let list = this.items;
            if (this.filterMode !== 'all') {
                list = list.filter(i => this.filterMode === 'image' ? i.isImage : ! i.isImage);
            }
            if (this.search) {
                const s = this.search.toLowerCase();
                list = list.filter(i => (i.name || '').toLowerCase().includes(s));
            }
            return list;
        },
        get totalItems() { return this.baseList.length; },
        get totalPages() { return Math.max(1, Math.ceil(this.totalItems / this.perPage)); },
        get filtered() {
            const start = (this.page - 1) * this.perPage;
            return this.baseList.slice(start, start + this.perPage);
        },
        selectedItem() {
            if (this.multiple) return null;
            return this.items.find(i => i.id == this.state) || null;
        },
        isSelected(id) {
            if (this.multiple) return Array.isArray(this.state) && this.state.includes(id);
            return this.state == id;
        },
        toggle(id) {
            if (this.multiple) {
                this.state = Array.isArray(this.state) ? this.state.slice() : [];
                const i = this.state.indexOf(id);
                if (i === -1) this.state.push(id); else this.state.splice(i, 1);
            } else {
                this.state = (this.state == id) ? null : id;
            }
        },
        fmtSize(bytes) {
            if (! bytes || bytes === 0) return '0 B';
            const units = ['B','KB','MB','GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(1024));
            return (bytes / Math.pow(1024, i)).toFixed(i > 0 ? 1 : 0) + ' ' + units[i];
        },
        badgeColor(ext) {
            const e = (ext || '').toLowerCase();
            if (['pdf'].includes(e)) return '#ef4444';
            if (['xls','xlsx','csv'].includes(e)) return '#10b981';
            if (['doc','docx'].includes(e)) return '#3b82f6';
            if (['ppt','pptx'].includes(e)) return '#f59e0b';
            if (['zip','rar','7z','tar','gz'].includes(e)) return '#6b7280';
            return '#94a3b8';
        },
        upload(event) {
            const file = event.target.files[0];
            if (! file) return;
            const fd = new FormData();
            fd.append('file', file);
            fd.append('_token', this.csrf);
            if (this.directory) fd.append('directory', this.directory);
            this.uploading = true;
            fetch(this.uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': this.csrf },
                body: fd,
                credentials: 'same-origin',
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.id) {
                    this.items.unshift({ id: data.id, url: data.url, name: data.name, isImage: data.isImage, ext: data.ext || '', size: data.size || 0 });
                    this.page = 1;
                    this.toggle(data.id);
                }
            })
            .catch(() => {})
            .finally(() => { this.uploading = false; event.target.value = ''; });
        }
    }"
    x-init="init()"
    class="media-picker"
>

    {{-- Label --}}
    @if($getLabel())
        <label class="fi-fo-field-label block text-sm font-medium text-gray-900 dark:text-gray-100 mb-1.5">
            {{ $getLabel() }}
        </label>
    @endif

    {{-- ========== 触发器 ========== --}}
    <div class="media-picker-trigger max-w-full">

        {{-- 已选中：单选（与未选中卡片尺寸/内边距完全一致，仅边框/底色/图标内容不同） --}}
        <template x-if="selectedItem() && ! multiple">
            <div @click="open = true"
                 style="display:flex;align-items:center;gap:12px;padding:12px;border:2px solid;border-color:var(--primary-300);border-radius:12px;background:var(--primary-50);cursor:pointer;max-width:100%;box-sizing:border-box;transition:border-color 0.15s, background 0.15s;"
                 @mouseenter="$el.style.borderColor='var(--primary-400)'"
                 @mouseleave="$el.style.borderColor='var(--primary-300)'">
                <template x-if="selectedItem().isImage">
                    <img :src="selectedItem().url" :alt="selectedItem().name"
                         style="width:48px;height:48px;flex-shrink:0;border-radius:10px;object-fit:cover;" loading="lazy" />
                </template>
                <template x-if="! selectedItem().isImage">
                    <div style="width:48px;height:48px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--primary-100);">
                        <svg style="width:24px;height:24px;color:var(--primary-600);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </div>
                </template>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:14px;font-weight:600;color:#4b5563;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="selectedItem().name"></p>
                    <p style="font-size:12px;color:#9ca3af;margin-top:2px;" x-text="fmtSize(selectedItem().size)"></p>
                </div>
                {{-- 清空按钮 --}}
                <button type="button" @click.stop="state = null"
                    style="flex-shrink:0;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:transparent;color:#9ca3af;border:none;cursor:pointer;"
                    aria-label="@lang('media-library::picker.clear')"
                    @mouseenter="$el.style.background='#f3f4f6';$el.style.color='#ef4444';"
                    @mouseleave="$el.style.background='transparent';$el.style.color='#9ca3af';">
                    <svg style="width:14px;height:14px;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </template>

        {{-- 已选中：多选 --}}
        <template x-if="multiple && Array.isArray(state) && state.length > 0">
            <div class="flex flex-wrap items-center gap-2 rounded-xl border-2 p-3"
                 style="max-width:100%;border-color:var(--primary-300);background:var(--primary-50);">
                <template x-for="it in items.filter(i => state.includes(i.id))" :key="it.id">
                    <div style="position:relative;width:56px;height:56px;border-radius:10px;overflow:hidden;flex-shrink:0;border:2px solid var(--primary-300);">
                        <template x-if="it.isImage">
                            <img :src="it.url" :alt="it.name" @click="open = true"
                                 style="width:56px;height:56px;object-fit:cover;cursor:pointer;display:block;" loading="lazy" :title="it.name" />
                        </template>
                        <template x-if="! it.isImage">
                            <div @click="open = true" :title="it.name"
                                 style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;cursor:pointer;border-radius:10px;font-size:10px;font-weight:700;text-transform:uppercase;"
                                 :style="'color:#fff;background:' + (badgeColor(it.ext) || '#94a3b8')" x-text="(it.ext || '?')"></div>
                        </template>
                        <button type="button" @click="toggle(it.id)"
                            style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:9999px;background:#ef4444;color:#fff;font-size:11px;border:2px solid #fff;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">&times;</button>
                    </div>
                </template>
                <button type="button" @click="open = true"
                    style="display:flex;align-items:center;gap:6px;height:40px;padding:0 12px;border:2px dashed;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;border-color:var(--primary-300);color:var(--primary-600);background:#fff;">
                    <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    @lang('media-library::picker.choose')
                </button>
            </div>
        </template>

        {{-- 未选中：空卡片（state 为空，或 state 存在但不对应任何有效媒体——如遗留的路径字符串 --}}
        <template x-if="(! multiple && (! state || ! selectedItem())) || (multiple && (!Array.isArray(state) || state.length === 0))">
            <div @click="open = true"
                 style="display:flex;align-items:center;gap:12px;padding:12px;border:2px solid #d1d5db;border-radius:12px;background:#f9fafb;cursor:pointer;max-width:100%;box-sizing:border-box;transition:border-color 0.15s, background 0.15s;"
                 @mouseenter="$el.style.borderColor='var(--primary-300)';$el.style.background='var(--primary-50)';"
                 @mouseleave="$el.style.borderColor='#d1d5db';$el.style.background='#f9fafb';">
                <div style="width:48px;height:48px;display:flex;align-items:center;justify-content:center;border-radius:10px;background:var(--primary-100);flex-shrink:0;">
                    <svg style="width:24px;height:24px;color:var(--primary-600);" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5c0 1.247 1.006 2.25 2.25 2.25Z" /></svg>
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="font-size:14px;font-weight:600;color:#4b5563;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">@lang('media-library::picker.choose')</p>
                    <p style="font-size:12px;color:#9ca3af;margin-top:2px;" x-text="titleText"></p>
                </div>
                <svg style="width:20px;height:20px;color:#d1d5db;flex-shrink:0;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
            </div>
        </template>
    </div>

    {{-- ========== 弹窗（x-teleport 移到 body，保留 Alpine 作用域） ========== --}}
    <template x-teleport="body">
    <div data-picker-modal x-show="open" x-cloak
        class="picker-backdrop"
        style="position:fixed!important;top:0!important;left:0!important;right:0!important;bottom:0!important;width:100%!important;height:100%!important;z-index:9999!important;background:rgba(0,0,0,0.45);backdrop-filter:blur(2px);overflow:hidden;"
        @click.self="open = false"
        x-trap.noscroll="open"
    >
        <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:100%;max-width:min(920px, 95vw);max-height:90vh;display:flex;flex-direction:column;overflow:hidden;border-radius:16px;background:#fff;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);"
             @click.stop role="dialog" aria-modal="true">

            {{-- Header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #f3f4f6;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:8px;background:var(--primary-100);">
                        <svg style="width:18px;height:18px;color:var(--primary-600);" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5c0 1.247 1.006 2.25 2.25 2.25Z" /></svg>
                    </div>
                    <h3 style="font-size:16px;font-weight:600;color:#111827;" x-text="titleText"></h3>
                </div>
                <button type="button" @click="open = false"
                    style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:9999px;color:#9ca3af;cursor:pointer;border:none;background:transparent;"
                    aria-label="close"
                    @mouseenter="$el.style.background='#f3f4f6';$el.style.color='#4b5563';"
                    @mouseleave="$el.style.background='transparent';$el.style.color='#9ca3af';">
                    <svg style="width:20px;height:20px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            {{-- Tab 过滤（仅真正的混合选择器显示） --}}
            @if($shouldShowTabs())
            <div style="padding:12px 20px;border-bottom:1px solid #f3f4f6;">
                <div class="fi-picker-seg">
                    <button type="button" class="fi-picker-seg-btn" :class="filterMode === 'all' ? 'active' : ''" @click="filterMode = 'all'"
                        >@lang('media-library::picker.tab_all')</button>
                    <button type="button" class="fi-picker-seg-btn" :class="filterMode === 'image' ? 'active' : ''" @click="filterMode = 'image'"
                        >@lang('media-library::picker.tab_image')</button>
                    <button type="button" class="fi-picker-seg-btn" :class="filterMode === 'file' ? 'active' : ''" @click="filterMode = 'file'"
                        >@lang('media-library::picker.tab_file')</button>
                </div>
            </div>
            @endif

            {{-- Toolbar --}}
            <div style="display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid #f3f4f6;">
                <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.05);border:none;transition:background 0.15s;color:#fff;background:var(--primary-400);"
                    @mouseenter="$el.style.background='var(--primary-300)'"
                    @mouseleave="$el.style.background='var(--primary-400)'">
                    <svg style="width:16px;height:16px;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                    <span x-show="! uploading">@lang('media-library::picker.upload')</span>
                    <span x-show="uploading" style="display:flex;align-items:center;gap:4px;">
                        <svg style="width:14px;height:14px;animation:spin 1s linear infinite;" fill="none" viewBox="0 0 24 24"><circle opacity="0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path opacity="0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        @lang('media-library::picker.uploading')
                    </span>
                    <input type="file" style="display:none;" :accept="allowedTypes.length ? allowedTypes.join(',') : '*'" @change="upload($event)" :disabled="uploading" />
                </label>
                <div style="position:relative;flex:1;">
                    <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:16px;height:16px;color:#9ca3af;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    <input type="text" x-model="search" :placeholder="@js(__('media-library::picker.search'))"
                        style="width:100%;padding:8px 12px 8px 36px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;outline:none;box-sizing:border-box;transition:border-color 0.15s, box-shadow 0.15s;"
                        onfocus="this.style.borderColor='var(--primary-500)';this.style.boxShadow='0 0 0 3px var(--primary-100)';"
                        onblur="this.style.borderColor='#e5e7eb';this.style.boxShadow='none';" />
                </div>
            </div>

            {{-- Grid：自适应列数，永不横向滚动 --}}
            <div style="overflow-y:auto;overflow-x:hidden;padding:14px;display:grid;grid-template-columns:repeat(auto-fill, minmax(130px, 1fr));gap:12px;max-height:520px;">
                <template x-for="item in filtered" :key="item.id">
                    <button type="button" @click="toggle(item.id)"
                        style="position:relative;overflow:hidden;border-radius:12px;border:2px solid;text-align:left;transition:all 0.15s;cursor:pointer;background:#fff;padding:0;"
                        :style="isSelected(item.id)
                            ? 'border-color:var(--primary-500);background:var(--primary-50);box-shadow:0 0 0 3px var(--primary-200),0 4px 6px -1px rgba(0,0,0,0.08);'
                            : 'border-color:#e5e7eb;'"
                        @mouseenter="if(!isSelected(item.id)){$el.style.borderColor='#d1d5db';$el.style.boxShadow='0 1px 2px rgba(0,0,0,0.05)'}"
                        @mouseleave="if(!isSelected(item.id)){$el.style.borderColor='#e5e7eb';$el.style.boxShadow='none'}">

                        {{-- 图片卡：3:4 比例，完整显示，四角圆角 --}}
                        <template x-if="item.isImage">
                            <div style="position:relative;width:100%;aspect-ratio:3/4;overflow:hidden;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-radius:10px;">
                                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;padding:8px;border-radius:10px;">
                                    <img :src="item.url" :alt="item.name"
                                         style="max-width:100%;max-height:100%;object-fit:contain;transition:transform 0.2s;border-radius:6px;"
                                         loading="lazy" />
                                </div>
                                <span style="position:absolute;left:6px;bottom:6px;width:24px;height:24px;display:flex;align-items:center;justify-content:center;border-radius:9999px;background:rgba(255,255,255,0.95);box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                                    <svg style="width:13px;height:13px;color:var(--primary-600);" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5c0 1.247 1.006 2.25 2.25 2.25Z" /></svg>
                                </span>
                            </div>
                        </template>

                        {{-- 文件卡：与图片卡同尺寸（aspect-ratio:3/4），图标居中、扩展名位于图标下方并居中 --}}
                        <template x-if="! item.isImage">
                            <div style="position:relative;width:100%;aspect-ratio:3/4;overflow:hidden;background:linear-gradient(135deg,#f8fafc,#f1f5f9);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;">
                                <svg style="width:48px;height:48px;color:#94a3b8;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;box-shadow:0 1px 2px rgba(0,0,0,0.08);"
                                      :style="'color:' + (badgeColor(item.ext) || '#94a3b8')"
                                      x-text="(item.ext || '?')"></span>
                            </div>
                        </template>

                        {{-- 信息行 --}}
                        <div style="padding:6px 10px 8px;">
                            <p style="font-size:12px;font-weight:600;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="item.name" :title="item.name"></p>
                            <p style="margin-top:2px;font-size:11px;color:#9ca3af;" x-text="fmtSize(item.size)"></p>
                        </div>
                    </button>
                </template>
                <template x-if="filtered.length === 0">
                    <div style="grid-column:1 / -1;display:flex;flex-direction:column;align-items:center;gap:10px;padding:48px 0;color:#9ca3af;">
                        <svg style="width:56px;height:56px;opacity:0.25;" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5c0 1.247 1.006 2.25 2.25 2.25Z" /></svg>
                        <span style="font-size:14px;font-weight:500;">@lang('media-library::picker.empty')</span>
                    </div>
                </template>
            </div>

            {{-- 分页条 --}}
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 20px;border-top:1px solid #f3f4f6;">
                <span style="font-size:13px;color:#6b7280;" x-text="@js(__('media-library::picker.pager_total')).replace('{n}', totalItems)"></span>
                <div style="display:flex;align-items:center;gap:6px;" x-show="totalPages > 1">
                    <button type="button" class="fi-picker-page" @click="if (page > 1) page--" :disabled="page <= 1"
                        >@lang('media-library::picker.pager_prev')</button>
                    <template x-for="p in totalPages" :key="p">
                        <button type="button" class="fi-picker-page" :class="page === p ? 'active' : ''" @click="page = p"
                            x-text="p"></button>
                    </template>
                    <button type="button" class="fi-picker-page" @click="if (page < totalPages) page++" :disabled="page >= totalPages"
                        >@lang('media-library::picker.pager_next')</button>
                </div>
            </div>

            {{-- Footer --}}
                <div style="display:flex;align-items:center;gap:12px;padding:14px 20px;border-top:1px solid #f3f4f6;background:#f9fafb;">
                {{-- 多选计数 --}}
                <div x-show="multiple" style="font-size:13px;color:#6b7280;">
                    <span x-text="@js(__('media-library::picker.selected')).replace('{n}', Array.isArray(state) ? state.length : 0)"></span>
                </div>

                <div style="margin-left:auto;display:flex;gap:8px;">
                    <button type="button" @click="open = false"
                        style="border:1px solid #e5e7eb;background:#fff;border-radius:8px;padding:8px 16px;font-size:13px;font-weight:500;color:#4b5563;cursor:pointer;">
                        @lang('media-library::picker.cancel')
                    </button>
                    <button type="button" @click="open = false"
                        style="background:var(--primary-400);color:#fff;border-radius:8px;padding:8px 20px;font-size:13px;font-weight:600;border:none;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.05);"
                        @mouseenter="$el.style.background='var(--primary-300)'"
                        @mouseleave="$el.style.background='var(--primary-400)'">
                        @lang('media-library::picker.done')
                    </button>
                </div>
            </div>
        </div>
    </div>
    </template>
</div>
