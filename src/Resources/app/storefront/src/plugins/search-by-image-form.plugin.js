import Plugin from 'src/plugin-system/plugin.class';

export default class SearchByImageFormPlugin extends Plugin {
    static options = {

    }

    init() {
        this._searchByImageBtn = this.el.querySelector('.header-search-image-btn');
        this._form = document.querySelector('#search-by-image-form');

        this._fileField = this._form.querySelector('.header-search-image-file');

        this._searchByImageBtn.addEventListener('click', e => {
            this._fileField.click();
        });

        this._fileField.addEventListener('change', e => {
            this._form.submit();
        });
    }
}
