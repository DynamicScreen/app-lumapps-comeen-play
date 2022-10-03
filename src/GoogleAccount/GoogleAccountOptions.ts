import {
  ISlideOptionsContext,
  SlideOptionsModule, VueInstance
} from "@comeen/comeen-play-sdk-js";

export default class GoogleAccountOptionsModule extends SlideOptionsModule {
  constructor(context: ISlideOptionsContext) {
    super(context);
  }

  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideOptionsContext) {
const en = require("/home/scleriot/Dev/dynamicscreen/app-server/storage/apps//app-lumapps-comeen-play/0.2.0/languages/en.json");
const fr = require("/home/scleriot/Dev/dynamicscreen/app-server/storage/apps//app-lumapps-comeen-play/0.2.0/languages/fr.json");
const translator: any = this.context.translator;
translator.addResourceBundle('en', 'lumapps-google-account', en);
translator.addResourceBundle('fr', 'lumapps-google-account', fr);
this.t = (key: string, namespace: string = 'lumapps-google-account') => translator.t(key, {ns: namespace});

    const { h } = vue;

    const update = context.update;

    const { Field, TextInput, Toggle } = this.context.components

    return () =>
      h("div", {}, [
        h(Field, { label: 'LumApps API endpoint' },
          h(TextInput, { ...update.option('endpoint_uri'), placeholder: 'https://' })
        )
      ]
    )
  }
}
