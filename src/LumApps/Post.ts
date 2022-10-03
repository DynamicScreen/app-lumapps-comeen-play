import {
  ISlideContext,
  IPublicSlide,
  SlideModule,
  VueInstance
} from "@comeen/comeen-play-sdk-js";

import { nextTick } from 'vue';

export default class PostSlideModule extends SlideModule {
  constructor(context: ISlideContext) {
    super(context);
  }

  async onReady() {
    return true;
  };

  setup(props: Record<string, any>, vue: VueInstance, context: ISlideContext) {
    const { h, reactive, ref } = vue;

    const slide = reactive(props.slide) as IPublicSlide;
    this.context = reactive(props.slide.context);

    // const url = ref(slide.data.url);

    this.context.onPrepare(async () => {
    });

    this.context.onReplay(async () => {
    });

    this.context.onPlay(async () => {
    });

    this.context.onPause(async () => {
    });
    this.context.onResume(async () => {
    });

    this.context.onEnded(async () => {
    });

    return () =>
      h("div", {
        class: "flex w-full h-full"
      }, [
      ])
  }
}
